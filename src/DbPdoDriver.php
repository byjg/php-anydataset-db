<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\Helpers\SqlBind;
use ByJG\AnyDataset\Db\Helpers\SqlHelper;
use ByJG\AnyDataset\Db\Traits\DbCacheTrait;
use ByJG\AnyDataset\Db\Traits\TransactionTrait;
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
    use TransactionTrait;
    use DbCacheTrait;

    /**
     * @var PDO
     */
    protected $instance = null;

    /**
     * @var PDOStatement[]
     */
    protected $supportMultRowset = false;


    const DONT_PARSE_PARAM = "dont_parse_param";
    const STATEMENT_CACHE = "stmtcache";
    const UNIX_SOCKET = "unix_socket";

    /**
     * @var PdoObj
     */
    protected $pdoObj;

    protected $preOptions;

    protected $postOptions;
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

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
        $this->pdoObj = new PdoObj($connUri);
        $this->preOptions = $preOptions;
        $this->postOptions = $postOptions;
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
        $this->instance = $this->pdoObj->createInstance();

        return true;
    }

    public function disconnect()
    {
        $this->clearCache();
        $this->instance = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     *
     * @param string $sql
     * @param array $array
     * @return PDOStatement
     */
    protected function getDBStatement($sql, $array = null)
    {
        if (!$this->getUri()->hasQueryKey(self::DONT_PARSE_PARAM)) {
            list($sql, $array) = SqlBind::parseSQL($this->pdoObj->getUri(), $sql, $array);
        }

        if ($this->pdoObj->expectToCacheResults()) {
            $this->isConnected(true, true);
            $stmt = $this->getOrSetSqlCacheStmt($sql);
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
            $this->dbHelper = Factory::getDbFunctions($this->pdoObj->getUri());
        }
        return $this->dbHelper;
    }

    public function getUri()
    {
        return $this->pdoObj->getUri();
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

    protected function array_map_assoc($callback, $array)
    {
        $r = array();
        foreach ($array as $key=>$value) {
            $r[$key] = $callback($key, $value);
        }
        return $r;
    }

    protected function getQueryKey($sql, $array)
    {
        $key1 = md5($sql);
        $key2 = "";

        // Check which parameter exists in the SQL
        if (is_array($array)) {
            $key2 = md5(":" . implode(',', $this->array_map_assoc(function($k,$v){return "$k:$v";},$array)));
        }

        return  "qry:" . $key1 . $key2;
    }
}
