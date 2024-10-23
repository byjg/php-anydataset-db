<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Core\Exception\NotImplementedException;
use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\Helpers\SqlBind;
use ByJG\AnyDataset\Db\Helpers\SqlHelper;
use ByJG\AnyDataset\Db\Traits\DbCacheTrait;
use ByJG\AnyDataset\Db\Traits\TransactionTrait;
use ByJG\Util\Uri;
use DateInterval;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

class DbOci8Driver implements DbDriverInterface
{
    use TransactionTrait;
    use DbCacheTrait;

    private LoggerInterface $logger;

    private ?DbFunctionsInterface $dbHelper = null;

    public static function schema(): array
    {
        return ['oci8'];
    }

    /**
     * Enter description here...
     *
     * @var Uri
     */
    protected Uri $connectionUri;

    /**
     * @var resource|false
     */
    protected mixed $conn;
    protected int $ociAutoCommit;

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
        /** @psalm-suppress UndefinedConstant */
        $this->ociAutoCommit = OCI_COMMIT_ON_SUCCESS;
        $this->reconnect();
    }

    /**
     *
     * @param Uri $connUri
     * @return string
     */
    public static function getTnsString(Uri $connUri): string
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
     * @param string $sql
     * @param array|null $array
     * @return resource
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     */
    protected function getOci8Cursor(string $sql, array $array = null)
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
     * @param string $sql
     * @param array|null $params
     * @param CacheInterface|null $cache
     * @param int|DateInterval $ttl
     * @return GenericIterator
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     */
    public function getIterator(string $sql, ?array $params = null, ?CacheInterface $cache = null, DateInterval|int $ttl = 60): GenericIterator
    {
        return $this->getIteratorUsingCache($sql, $params, $cache, $ttl, function ($sql, $params) {
            $cur = $this->getOci8Cursor($sql, $params);
            return new Oci8Iterator($cur);
        });
    }

    /**
     * @param string $sql
     * @param array|null $array
     * @return mixed
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     */
    public function getScalar(string $sql, ?array $array = null): mixed
    {
        $cur = $this->getOci8Cursor($sql, $array);

        /** @psalm-suppress UndefinedConstant */
        $row = oci_fetch_array($cur, OCI_RETURN_NULLS);
        if ($row) {
            $scalar = $row[0];
        } else {
            $scalar = false;
        }

        oci_free_cursor($cur);

        return $scalar;
    }

    /**
     * @param string $tablename
     * @return array
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     */
    public function getAllFields(string $tablename): array
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

    protected function transactionHandler(TransactionStageEnum $action, string $isoLevelCommand = ""): void
    {
        switch ($action) {
            case TransactionStageEnum::begin:
                /** @psalm-suppress UndefinedConstant */
                $this->ociAutoCommit = OCI_NO_AUTO_COMMIT;
                $this->execute($isoLevelCommand);
                break;

            case TransactionStageEnum::commit:
                /** @psalm-suppress UndefinedConstant */
                if ($this->ociAutoCommit == OCI_COMMIT_ON_SUCCESS) {
                    throw new DatabaseException('No transaction for commit');
                }

                /** @psalm-suppress UndefinedConstant */
                $this->ociAutoCommit = OCI_COMMIT_ON_SUCCESS;

                $result = oci_commit($this->conn);
                if (!$result) {
                    $error = oci_error($this->conn);
                    throw new DatabaseException($error['message']);
                }
                break;

            case TransactionStageEnum::rollback:
                /** @psalm-suppress UndefinedConstant */
                if ($this->ociAutoCommit == OCI_COMMIT_ON_SUCCESS) {
                    throw new DatabaseException('No transaction for rollback');
                }

                /** @psalm-suppress UndefinedConstant */
                $this->ociAutoCommit = OCI_COMMIT_ON_SUCCESS;

                oci_rollback($this->conn);
                break;
        }
    }
    
    /**
     * @param string $sql
     * @param array|null $array
     * @return bool
     * @throws DatabaseException
     */
    public function execute(string $sql, ?array $array = null): bool
    {
        $cur = $this->getOci8Cursor($sql, $array);
        oci_free_cursor($cur);
        return true;
    }

    /**
     *
     * @return resource|false
     */
    public function getDbConnection(): mixed
    {
        return $this->conn;
    }

    /**
     * @param string $name
     * @throws NotImplementedException
     */
    public function getAttribute(string $name): mixed
    {
        throw new NotImplementedException('Method not implemented for OCI Driver');
    }

    /**
     * @param string $name
     * @param mixed $value
     * @throws NotImplementedException
     */
    public function setAttribute(string $name, mixed $value): void
    {
        throw new NotImplementedException('Method not implemented for OCI Driver');
    }

    /**
     * @param string $sql
     * @param array|null $array
     * @throws NotImplementedException
     */
    public function executeAndGetId(string $sql, ?array $array = null): mixed
    {
        return $this->getDbHelper()->executeAndGetInsertedId($this, $sql, $array);
    }

    /**
     * @return DbFunctionsInterface
     */
    public function getDbHelper(): DbFunctionsInterface
    {
        if (empty($this->dbHelper)) {
            $this->dbHelper = Factory::getDbFunctions($this->getUri());
        }
        return $this->dbHelper;
    }

    /**
     * @return Uri
     */
    public function getUri(): Uri
    {
        return $this->connectionUri;
    }

    /**
     * @throws NotImplementedException
     */
    public function isSupportMultiRowset(): bool
    {
        return false;
    }

    /**
     * @param bool $multipleRowSet
     * @throws NotImplementedException
     */
    public function setSupportMultiRowset(bool $multipleRowSet): void
    {
        throw new NotImplementedException('Method not implemented for OCI Driver');
    }

    public function reconnect(bool $force = false): bool
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

        $connectMethod = match ($connType) {
            "persistent" => "oci_pconnect",
            "new" => "oci_new_connect",
            default => "oci_connect",
        };

        /** @psalm-suppress UndefinedConstant */
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

    public function disconnect(): void
    {
        $this->clearCache();
        $this->conn = null;
    }

    public function isConnected(bool $softCheck = false, bool $throwError = false): bool
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

    public function enableLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function log(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }
}