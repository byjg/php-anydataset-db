<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\Helpers\SqlBind;
use ByJG\AnyDataset\Db\Helpers\SqlHelper;
use ByJG\AnyDataset\Lists\ArrayDataset;
use ByJG\Util\Uri;
use Exception;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

abstract class DbPdoDriver implements DbDriverInterface
{

    use DbCacheTrait;

    /**
     * @var PDO
     */
    protected $instance = null;

    /**
     * @var PDOStatement[]
     */
    protected $stmtCache = [];

    protected $maxStmtCache = 10;

    protected $useStmtCache = false;

    protected $supportMultRowset = false;

    const DONT_PARSE_PARAM = "dont_parse_param";
    const STATEMENT_CACHE = "stmtcache";

    /**
     * @var Uri
     */
    protected $connectionUri;

    protected $preOptions;

    protected $postOptions;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * DbPdoDriver constructor.
     *
     * @param Uri $connUri
     * @param array $preOptions
     * @param array $postOptions
     * @throws NotAvailableException
     */
    public function __construct(Uri $connUri, $preOptions = null, $postOptions = null)
    {
        $this->logger = new NullLogger();
        $this->connectionUri = $connUri;
        $this->preOptions = $preOptions;
        $this->postOptions = $postOptions;
        $this->validateConnUri();
        $this->reconnect();
    }

    public function reconnect($force = false)
    {
        if ($this->isConnected() && !$force) {
            return false;
        }

        // Release old instance
        $this->disconnect();

        // Connect
        $this->createPdoInstance();

        return true;
    }

    public function disconnect()
    {
        $this->instance = null;
    }

    protected function createPdoInstance()
    {
        $pdoConnectionString = $this->createPdoConnStr($this->connectionUri);

        // Create Connection
        $this->instance = new PDO(
            $pdoConnectionString,
            $this->connectionUri->getUsername(),
            $this->connectionUri->getPassword(),
            (array)$this->preOptions
        );

        $this->connectionUri = $this->connectionUri->withScheme($this->getInstance()->getAttribute(PDO::ATTR_DRIVER_NAME));

        $this->setPdoDefaultParams($this->postOptions);
    }

    /**
     * @throws NotAvailableException
     */
    protected function validateConnUri()
    {
        if (!defined('PDO::ATTR_DRIVER_NAME')) {
            throw new NotAvailableException("Extension 'PDO' is not loaded");
        }

        $scheme = $this->connectionUri->getScheme();

        if ($this->connectionUri->getScheme() != "pdo" && !extension_loaded('pdo_' . strtolower($scheme))) {
            throw new NotAvailableException("Extension 'pdo_" . strtolower($this->connectionUri->getScheme()) . "' is not loaded");
        }

        if ($this->connectionUri->getQueryPart(self::STATEMENT_CACHE) == "true") {
            $this->useStmtCache = true;
        }
    }

    protected function setPdoDefaultParams($postOptions = [])
    {
        // Set Specific Attributes
        $defaultPostOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_CASE => PDO::CASE_LOWER,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
        $defaultPostOptions = $defaultPostOptions + (array)$postOptions;

        foreach ((array) $defaultPostOptions as $key => $value) {
            $this->getInstance()->setAttribute($key, $value);
        }
    }
    
    protected function createPdoConnStr(Uri $connUri)
    {
        if ($connUri->getScheme() == "pdo") {
            return $this->preparePdoConnectionStr($connUri->getHost(), ".", null, null, $connUri->getQuery());
        } else {
            return $this->preparePdoConnectionStr($connUri->getScheme(), $connUri->getHost(), $connUri->getPath(), $connUri->getPort(), $connUri->getQuery());
        }
    }

    public function preparePdoConnectionStr($scheme, $host, $database, $port, $query)
    {
        if (empty($host)) {
            return $scheme . ":" . $database;
        }

        $database = ltrim(empty($database) ? "" : $database, '/');
        if (!empty($database)) {
            $database = ";dbname=$database";
        }

        $pdoConnectionStr = $scheme . ":"
            . ($host != "." ? "host=" . $host : "")
            . $database;

        if (!empty($port)) {
            $pdoConnectionStr .= ";port=" . $port;
        }

        parse_str($query, $queryArr);
        unset($queryArr[self::DONT_PARSE_PARAM]);
        unset($queryArr[self::STATEMENT_CACHE]);
        if ($pdoConnectionStr[-1] != ":") {
            $pdoConnectionStr .= ";";
        }
        $pdoConnectionStr .= http_build_query($queryArr, "", ";");

        return $pdoConnectionStr;
    }
    
    public function __destruct()
    {
        $this->stmtCache = null;
        $this->instance = null;
    }

    /**
     *
     * @param string $sql
     * @param array $array
     * @return PDOStatement
     */
    protected function getDBStatement($sql, $array = null)
    {
        if (is_null($this->connectionUri->getQueryPart(self::DONT_PARSE_PARAM))) {
            list($sql, $array) = SqlBind::parseSQL($this->connectionUri, $sql, $array);
        }

        if ($this->useStmtCache) {
            if ($this->getMaxStmtCache() > 0 && !isset($this->stmtCache[$sql])) {
                $this->stmtCache[$sql] = $this->getInstance()->prepare($sql);
                if ($this->getCountStmtCache() > $this->getMaxStmtCache()) { //Kill old cache to get waste memory
                    array_shift($this->stmtCache);
                }
            }

            $this->isConnected(true, true);
            $stmt = $this->stmtCache[$sql];
        } else {
            $stmt = $this->getInstance()->prepare($sql);
        }

        if (!empty($array)) {
            foreach ($array as $key => $value) {
                $stmt->bindValue(":" . SqlBind::keyAdj($key), $value);
            }
        }

        $this->logger->debug("SQL: $sql\nParams: " . json_encode($array));

        return $stmt;
    }

    public function getIterator($sql, $params = null, CacheInterface $cache = null, $ttl = 60)
    {
        if (!empty($cache)) {
            // Otherwise try to get from cache
            $key = $this->getQueryKey($sql, $params);

            // Get the CACHE
            $cachedItem = $cache->get($key);
            if (!is_null($cachedItem)) {
                return (new ArrayDataset($cachedItem))->getIterator();
            }
        }


        $stmt = $this->getDBStatement($sql, $params);
        $stmt->execute();
        $iterator = new DbIterator($stmt);

        if (!empty($cache)) {
            $cachedItem = $iterator->toArray();
            $cache->set($key, $cachedItem, $ttl);
            return (new ArrayDataset($cachedItem))->getIterator();
        }

        return $iterator;
    }

    public function getScalar($sql, $array = null)
    {
        $stmt = $this->getDBStatement($sql, $array);
        $stmt->execute();

        $scalar = $stmt->fetchColumn();

        $stmt->closeCursor();

        return $scalar;
    }

    public function getAllFields($tablename)
    {
        $fields = array();
        $statement = $this->getInstance()->query(
            SqlHelper::createSafeSQL(
                "select * from @@table where 0=1",
                [
                    "@@table" => $tablename
                ]
            )
        );
        $fieldLength = $statement->columnCount();
        for ($i = 0; $i < $fieldLength; $i++) {
            $fld = $statement->getColumnMeta($i);
            $fields[] = strtolower($fld ["name"]);
        }
        return $fields;
    }

    public function beginTransaction()
    {
        $this->logger->debug("SQL: Begin transaction");
        $this->getInstance()->beginTransaction();
    }

    public function commitTransaction()
    {
        $this->logger->debug("SQL: Commit transaction");
        $this->getInstance()->commit();
    }

    public function rollbackTransaction()
    {
        $this->logger->debug("SQL: Rollback transaction");
        $this->getInstance()->rollBack();
    }

    public function execute($sql, $array = null)
    {
        $stmt = $this->getDBStatement($sql, $array);
        $result = $stmt->execute();

        if ($this->isSupportMultRowset()) {
            // Check error
            do {
                // This loop is only to throw an error (if exists)
                // in case of execute multiple queries
            } while ($stmt->nextRowset());
        }

        return $result;
    }

    public function executeAndGetId($sql, $array = null)
    {
        return $this->getDbHelper()->executeAndGetInsertedId($this, $sql, $array);
    }

    /**
     *
     * @return PDO
     */
    public function getDbConnection()
    {
        return $this->instance;
    }

    public function getAttribute($name)
    {
        $this->getInstance()->getAttribute($name);
    }

    public function setAttribute($name, $value)
    {
        $this->getInstance()->setAttribute($name, $value);
    }

    protected $dbHelper;

    public function getDbHelper()
    {
        if (empty($this->dbHelper)) {
            $this->dbHelper = Factory::getDbFunctions($this->connectionUri);
        }
        return $this->dbHelper;
    }

    public function getUri()
    {
        return $this->connectionUri;
    }

    /**
     * @return bool
     */
    public function isSupportMultRowset()
    {
        return $this->supportMultRowset;
    }

    /**
     * @param bool $multipleRowSet
     */
    public function setSupportMultRowset($multipleRowSet)
    {
        $this->supportMultRowset = $multipleRowSet;
    }

    /**
     * @return int
     */
    public function getMaxStmtCache()
    {
        return $this->maxStmtCache;
    }

    public function getCountStmtCache()
    {
        return count($this->stmtCache);
    }

    /**
     * @param int $maxStmtCache
     */
    public function setMaxStmtCache($maxStmtCache)
    {
        $this->maxStmtCache = $maxStmtCache;
    }

    public function isConnected($softCheck = false, $throwError = false)
    {
        if (empty($this->instance)) {
            if ($throwError) {
                throw new DbDriverNotConnected('DbDriver not connected');
            }
            return false;
        }

        if ($softCheck) {
            return true;
        }

        try {
            $this->instance->query("SELECT 1"); // Do not use $this->getInstance()
        } catch (Exception $ex) {
            if ($throwError) {
                throw new DbDriverNotConnected('DbDriver not connected');
            }
            return false;
        }

        return true;
    }

    protected function getInstance()
    {
        $this->isConnected(true, true);
        return $this->instance;
    }

    public function enableLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function log($message, $context = [])
    {
        $this->logger->debug($message, $context);
    }
}
