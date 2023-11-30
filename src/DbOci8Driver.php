<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Core\Exception\NotImplementedException;
use ByJG\AnyDataset\Db\Helpers\SqlBind;
use ByJG\AnyDataset\Db\Helpers\SqlHelper;
use ByJG\Util\Uri;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DbOci8Driver implements DbDriverInterface
{

    private LoggerInterface $logger;

    public function getMaxStmtCache() { }

    public function getCountStmtCache() { }

    public function setMaxStmtCache($maxStmtCache) { }

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
    protected $transaction = OCI_COMMIT_ON_SUCCESS;

    /**
     * Ex.
     *
     *    oci8://username:password@host:1521/servicename?protocol=TCP&codepage=WE8MSWIN1252
     *
     * @param Uri $connectionString
     * @throws DatabaseException
     */
    public function __construct(Uri $connectionString)
    {
        $this->logger = new NullLogger();
        $this->connectionUri = $connectionString;

        $codePage = $this->connectionUri->getQueryPart("codepage");
        $codePage = ($codePage == "") ? 'UTF8' : $codePage;

        $tns = DbOci8Driver::getTnsString($this->connectionUri);

        $this->conn = oci_connect(
            $this->connectionUri->getUsername(),
            $this->connectionUri->getPassword(),
            $tns,
            $codePage
        );

        if (!$this->conn) {
            $error = oci_error();
            throw new DatabaseException($error['message']);
        }
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
            "        (CONNECT_DATA = (SERVICE_NAME = $svcName)) " .
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
        list($query, $array) = SqlBind::parseSQL($this->connectionUri, $sql, $array);

        $this->logger->debug("SQL: $query");

        // Prepare the statement
        $stid = oci_parse($this->conn, $query);
        if (!$stid) {
            $error = oci_error($this->conn);
            throw new DatabaseException($error['message']);
        }

        // Bind the parameters
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                oci_bind_by_name($stid, ":$key", $value);
            }
        }

        // Perform the logic of the query
        $result = oci_execute($stid, $this->transaction);

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
    public function getIterator($sql, $params = null)
    {
        $cur = $this->getOci8Cursor($sql, $params);
        return new Oci8Iterator($cur);
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

    public function beginTransaction()
    {
        $this->logger->debug("SQL: Begin Transaction");
        $this->transaction = OCI_NO_AUTO_COMMIT;
    }

    /**
     * @throws DatabaseException
     */
    public function commitTransaction()
    {
        $this->logger->debug("SQL: Commit Transaction");
        if ($this->transaction == OCI_COMMIT_ON_SUCCESS) {
            throw new DatabaseException('No transaction for commit');
        }

        $this->transaction = OCI_COMMIT_ON_SUCCESS;

        $result = oci_commit($this->conn);
        if (!$result) {
            $error = oci_error($this->conn);
            throw new DatabaseException($error['message']);
        }
    }

    /**
     * @throws DatabaseException
     */
    public function rollbackTransaction()
    {
        $this->logger->debug("SQL: Rollback Transaction");
        if ($this->transaction == OCI_COMMIT_ON_SUCCESS) {
            throw new DatabaseException('No transaction for rollback');
        }

        $this->transaction = OCI_COMMIT_ON_SUCCESS;

        oci_rollback($this->conn);
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
        throw new NotImplementedException('Method not implemented for OCI Driver');
    }

    /**
     * @return \ByJG\AnyDataset\Db\DbFunctionsInterface|void
     * @throws NotImplementedException
     */
    public function getDbHelper()
    {
        throw new NotImplementedException('Method not implemented for OCI Driver');
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
        throw new NotImplementedException('Method not implemented for OCI Driver');
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
        throw new NotImplementedException('Method not implemented for OCI Driver');
    }

    public function disconnect()
    {
        throw new NotImplementedException('Method not implemented for OCI Driver');
    }

    public function isConnected($softCheck = false, $throwError = false)
    {
        throw new NotImplementedException('Method not implemented for OCI Driver');
    }

    public function enableLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
