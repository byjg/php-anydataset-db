<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Core\Exception\NotImplementedException;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\Helpers\SqlBind;
use ByJG\AnyDataset\Db\Helpers\SqlHelper;
use ByJG\AnyDataset\Db\Traits\DbCacheTrait;
use ByJG\AnyDataset\Db\Traits\TransactionTrait;
use ByJG\Util\Uri;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

class DbOci8Driver implements DbDriverInterface
{
    use TransactionTrait;
    use DbCacheTrait;

    private LoggerInterface $logger;

    private DbFunctionsInterface $dbHelper;

    public static function schema()
    {
        return ['oci8'];
    }

    /**
     * Enter description here...
     *
     * @var Uri
     */
    protected $connectionUri;

    /** Used for OCI8 connections * */
    protected $conn;
    protected $ociAutoCommit;

    /**
     * Ex.
     *
     *    oci8://username:password@host:1521/servicename?protocol=TCP&codepage=WE8MSWIN1252&conntype=persistent|new|default
     *
     * @param Uri $connectionString
     * @throws DatabaseException
     */
    public function __construct(Uri $connectionString)
    {
        $this->logger = new NullLogger();
        $this->connectionUri = $connectionString;
        $this->ociAutoCommit = OCI_COMMIT_ON_SUCCESS;
        $this->reconnect();
    }

    /**
     *
     * @param Uri $connUri
     * @return string
     */
    public static function getTnsString(Uri $connUri)
    {
        $protocol = $connUri->getQueryPart("protocol");
        $protocol = ($protocol == "") ? 'TCP' : $protocol;

        $port = $connUri->getPort();
        $port = ($port == "") ? 1521 : $port;

        $svcName = preg_replace('~^/~', '', $connUri->getPath());

        $host = $connUri->getHost();

        return "(DESCRIPTION = " .
            "    (ADDRESS = (PROTOCOL = $protocol)(HOST = $host)(PORT = $port)) " .
            "        (CONNECT_DATA = ".
            "            (SERVICE_NAME = $svcName) " .
            "        ) " .
            ")";
    }

    public function __destruct()
    {
        $this->conn = null;
    }

    /**
     * @param $sql
     * @param null $array
     * @return resource
     * @throws DatabaseException
     */
    protected function getOci8Cursor($sql, $array = null)
    {
        if (is_null($this->conn)) {
            throw new DbDriverNotConnected('Instance not connected');
        }
        list($query, $array) = SqlBind::parseSQL($this->connectionUri, $sql, $array);

        $this->logger->debug("SQL: $query, Params: " . json_encode($array));

        // Prepare the statement
        $query = rtrim($query, ' ;');
        $stid = oci_parse($this->conn, $query);
        if (!$stid) {
            $error = oci_error($this->conn);
            throw new DatabaseException($error['message']);
        }

        // Bind the parameters
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                oci_bind_by_name($stid, ":$key", $array[$key], -1);
            }
        }

        // Perform the logic of the query
        $result = oci_execute($stid, $this->ociAutoCommit);

        // Check if is OK;
        if (!$result) {
            $error = oci_error($stid);
            throw new DatabaseException($error['message']);
        }

        return $stid;
    }

    /**
     * @param $sql
     * @param null $params
     * @return Oci8Iterator
     * @throws DatabaseException
     */
    public function getIterator($sql, $params = null, CacheInterface $cache = null, $ttl = 60)
    {
        return $this->getIteratorUsingCache($sql, $params, $cache, $ttl, function ($sql, $params) {
            $cur = $this->getOci8Cursor($sql, $params);
            return new Oci8Iterator($cur);
        });
    }

    /**
     * @param $sql
     * @param null $array
     * @return null
     * @throws DatabaseException
     */
    public function getScalar($sql, $array = null)
    {
        $cur = $this->getOci8Cursor($sql, $array);

        $row = oci_fetch_array($cur, OCI_RETURN_NULLS);
        if ($row) {
            $scalar = $row[0];
        } else {
            $scalar = null;
        }

        oci_free_cursor($cur);

        return $scalar;
    }

    /**
     * @param $tablename
     * @return array
     * @throws DatabaseException
     */
    public function getAllFields($tablename)
    {
        $cur = $this->getOci8Cursor(SqlHelper::createSafeSQL("select * from :table", array(':table' => $tablename)));

        $ncols = oci_num_fields($cur);

        $fields = array();
        for ($i = 1; $i <= $ncols; $i++) {
            $fields[] = strtolower(oci_field_name($cur, $i));
        }

        oci_free_statement($cur);

        return $fields;
    }

    protected function transactionHandler($action, $isolLevelCommand = "")
    {
        switch ($action) {
            case 'begin':
                $this->ociAutoCommit = OCI_NO_AUTO_COMMIT;
                $this->execute($isolLevelCommand);
                break;

            case 'commit':
                if ($this->ociAutoCommit == OCI_COMMIT_ON_SUCCESS) {
                    throw new DatabaseException('No transaction for commit');
                }

                $this->ociAutoCommit = OCI_COMMIT_ON_SUCCESS;

                $result = oci_commit($this->conn);
                if (!$result) {
                    $error = oci_error($this->conn);
                    throw new DatabaseException($error['message']);
                }
                break;

            case 'rollback':
                if ($this->ociAutoCommit == OCI_COMMIT_ON_SUCCESS) {
                    throw new DatabaseException('No transaction for rollback');
                }

                $this->ociAutoCommit = OCI_COMMIT_ON_SUCCESS;

                oci_rollback($this->conn);
                break;
        }
    }
    
    /**
     * @param $sql
     * @param null $array
     * @return bool
     * @throws DatabaseException
     */
    public function execute($sql, $array = null)
    {
        $cur = $this->getOci8Cursor($sql, $array);
        oci_free_cursor($cur);
        return true;
    }

    /**
     *
     * @return resource
     */
    public function getDbConnection()
    {
        return $this->conn;
    }

    /**
     * @param $name
     * @throws NotImplementedException
     */
    public function getAttribute($name)
    {
        throw new NotImplementedException('Method not implemented for OCI Driver');
    }

    /**
     * @param $name
     * @param $value
     * @throws NotImplementedException
     */
    public function setAttribute($name, $value)
    {
        throw new NotImplementedException('Method not implemented for OCI Driver');
    }

    /**
     * @param $sql
     * @param null $array
     * @throws NotImplementedException
     */
    public function executeAndGetId($sql, $array = null)
    {
        return $this->getDbHelper()->executeAndGetInsertedId($this, $sql, $array);
    }

    /**
     * @return \ByJG\AnyDataset\Db\DbFunctionsInterface|void
     * @throws NotImplementedException
     */
    public function getDbHelper()
    {
        if (empty($this->dbHelper)) {
            $this->dbHelper = Factory::getDbFunctions($this->getUri());
        }
        return $this->dbHelper;
    }

    /**
     * @return Uri
     */
    public function getUri()
    {
        return $this->connectionUri;
    }

    /**
     * @throws NotImplementedException
     */
    public function isSupportMultRowset()
    {
        return false;
    }

    /**
     * @param $multipleRowSet
     * @throws NotImplementedException
     */
    public function setSupportMultRowset($multipleRowSet)
    {
        throw new NotImplementedException('Method not implemented for OCI Driver');
    }

    public function reconnect($force = false)
    {
        if ($this->isConnected() && !$force) {
            return false;
        }

        // Release old instance
        $this->disconnect();

        // Connect
        $codePage = $this->connectionUri->getQueryPart("codepage");
        $codePage = ($codePage == "") ? 'UTF8' : $codePage;
        $tns = DbOci8Driver::getTnsString($this->connectionUri);
        $connType = $this->connectionUri->getQueryPart("conntype");
        switch ($connType) {
            case "persistent":
                $connectMethod = "oci_pconnect";
                break;
            case "new":
                $connectMethod = "oci_new_connect";
                break;
            default:
                $connectMethod = "oci_connect";
                break;
        }

        $this->conn = $connectMethod(
            $this->connectionUri->getUsername(),
            $this->connectionUri->getPassword(),
            $tns,
            $codePage,
            $this->connectionUri->getQueryPart('session_mode') ?? OCI_DEFAULT
        );

        if (!$this->conn) {
            $error = oci_error();
            throw new DatabaseException($error['message']);
        }

        return true;
    }

    public function disconnect()
    {
        $this->clearCache();
        $this->conn = null;
    }

    public function isConnected($softCheck = false, $throwError = false)
    {
        if (empty($this->conn)) {
            if ($throwError) {
                throw new DbDriverNotConnected('DbDriver not connected');
            }
            return false;
        }

        if ($softCheck) {
            return true;
        }

        try {
            oci_parse($this->conn, "SELECT 1 FROM DUAL"); // Do not use $this->getInstance()
        } catch (Exception $ex) {
            if ($throwError) {
                throw new DbDriverNotConnected('DbDriver not connected');
            }
            return false;
        }

        return true;
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