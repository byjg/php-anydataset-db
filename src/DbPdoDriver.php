<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\Helpers\SqlBind;
use ByJG\AnyDataset\Db\Helpers\SqlHelper;
use ByJG\Util\Uri;
use PDO;
use PDOStatement;

abstract class DbPdoDriver implements DbDriverInterface
{

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

    /**
     * @var Uri
     */
    protected $connectionUri;

    /**
     * DbPdoDriver constructor.
     *
     * @param Uri $connUri
     * @param null $preOptions
     * @param null $postOptions
     * @throws NotAvailableException
     */
    public function __construct(Uri $connUri, $preOptions = null, $postOptions = null)
    {
        $this->connectionUri = $connUri;

        if (!defined('PDO::ATTR_DRIVER_NAME')) {
            throw new NotAvailableException("Extension 'PDO' is not loaded");
        }

        if (!extension_loaded('pdo_' . strtolower($connUri->getScheme()))) {
            throw new NotAvailableException("Extension 'pdo_" . strtolower($connUri->getScheme()) . "' is not loaded");
        }

        if ($connUri->getQueryPart("stmtcache") == "true") {
            $this->useStmtCache = true;
        }

        $strcnn = $this->createPboConnStr($connUri);

        $this->createPdoInstance($strcnn, $preOptions, $postOptions);
    }

    protected function createPdoInstance($pdoConnectionString, $preOptions = null, $postOptions = null)
    {
        // Create Connection
        $this->instance = new PDO(
            $pdoConnectionString,
            $this->connectionUri->getUsername(),
            $this->connectionUri->getPassword(),
            (array) $preOptions
        );

        $this->connectionUri = $this->connectionUri->withScheme($this->instance->getAttribute(PDO::ATTR_DRIVER_NAME));

        // Set Specific Attributes
        $this->instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->instance->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);

        foreach ((array) $postOptions as $key => $value) {
            $this->instance->setAttribute($key, $value);
        }
    }
    
    protected function createPboConnStr(Uri $connUri)
    {
        $host = $connUri->getHost();
        if (empty($host)) {
            return $connUri->getScheme() . ":" . $connUri->getPath();
        }

        $database = preg_replace('~^/~', '', $connUri->getPath());
        if (!empty($database)) {
            $database = ";dbname=$database";
        }

        $strcnn = $connUri->getScheme() . ":"
            . "host=" . $connUri->getHost()
            . $database;

        if ($connUri->getPort() != "") {
            $strcnn .= ";port=" . $connUri->getPort();
        }

        $query = $connUri->getQuery();
        $strcnn .= ";" . implode(';', explode('&', $query));

        return $strcnn;
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
        if (!empty($array)) {
            list($sql, $array) = SqlBind::parseSQL($this->connectionUri, $sql, $array);
        }
        if ($this->useStmtCache) {
            if ($this->getMaxStmtCache() > 0 && !isset($this->stmtCache[$sql])) {
                $this->stmtCache[$sql] = $this->instance->prepare($sql);
                if ($this->getCountStmtCache() > $this->getMaxStmtCache()) { //Kill old cache to get waste memory
                    array_shift($this->stmtCache);
                }
            }

            $stmt = $this->stmtCache[$sql];
        } else {
            $stmt = $this->instance->prepare($sql);
        }

        if (!empty($array)) {
            foreach ($array as $key => $value) {
                $stmt->bindValue(":" . SqlBind::keyAdj($key), $value);
            }
        }

        return $stmt;
    }

    public function getIterator($sql, $params = null)
    {
        $stmt = $this->getDBStatement($sql, $params);
        $stmt->execute();
        return new DbIterator($stmt);
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
        $statement = $this->instance->query(
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
        $this->instance->beginTransaction();
    }

    public function commitTransaction()
    {
        $this->instance->commit();
    }

    public function rollbackTransaction()
    {
        $this->instance->rollBack();
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
        $this->instance->getAttribute($name);
    }

    public function setAttribute($name, $value)
    {
        $this->instance->setAttribute($name, $value);
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
}
