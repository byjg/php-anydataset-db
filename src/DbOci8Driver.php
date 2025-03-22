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
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use Closure;
use DateInterval;
use Exception;
use InvalidArgumentException;
use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

class DbOci8Driver implements DbDriverInterface
{
    use DbCacheTrait;
    use TransactionTrait;

    private LoggerInterface $logger;

    private ?DbFunctionsInterface $dbHelper = null;

    #[Override]
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
     * @param array|null $params
     * @return resource
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     */
    #[Override]
    public function prepareStatement(string $sql, array $params = null, ?array &$cacheInfo = []): mixed
    {
        if (is_null($this->conn)) {
            throw new DbDriverNotConnected('Instance not connected');
        }
        list($query, $params) = SqlBind::parseSQL($this->connectionUri, $sql, $params);

        $this->logger->debug("SQL: $query, Params: " . json_encode($params));

        // Prepare the statement
        $query = rtrim($query, ' ;');
        $stid = oci_parse($this->conn, $query);
        if (!$stid) {
            $error = oci_error($this->conn);
            throw new DatabaseException($error['message']);
        }

        // Bind the parameters
        if (is_array($params)) {
            foreach ($params as $key => $value) {
                oci_bind_by_name($stid, ":$key", $params[$key], -1);
            }
        }

        return $stid;
    }

    #[Override]
    public function executeCursor(mixed $statement): void
    {
        // Perform the logic of the query
        $result = oci_execute($statement, $this->ociAutoCommit);

        // Check if is OK;
        if (!$result) {
            $error = oci_error($statement);
            throw new DatabaseException($error['message']);
        }
    }

    /**
     * @param mixed $sql
     * @param array|null $params
     * @param CacheInterface|null $cache
     * @param int|DateInterval $ttl
     * @param int $preFetch
     * @param string|null $entityClass
     * @param Closure|null $entityTransformer
     * @return GenericIterator
     * @throws FileException
     * @throws XmlUtilException
     * @throws InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    #[Override]
    public function getIterator(mixed $sql, ?array $params = null, ?CacheInterface $cache = null, DateInterval|int $ttl = 60, int $preFetch = 0, ?string $entityClass = null, ?Closure $entityTransformer = null): GenericIterator
    {
        if (is_resource($sql)) {
            return new Oci8Iterator($sql, $preFetch, $entityClass, $entityTransformer);
        }

        if (is_string($sql)) {
            $sql = new SqlStatement($sql);
            if (!empty($cache)) {
                $sql->withCache($cache, $this->getQueryKey($cache, $sql->getSql(), $params), $ttl);
            }
        } elseif (!($sql instanceof SqlStatement)) {
            throw new InvalidArgumentException("The SQL must be a cursor, string or a SqlStatement object");
        }

        return $sql->getIterator($this, $params, $preFetch, $entityClass, $entityTransformer);
    }

    /**
     * @param mixed $sql
     * @param array|null $array
     * @return mixed
     */
    #[Override]
    public function getScalar(mixed $sql, ?array $array = null): mixed
    {
        if (is_resource($sql)) {
            /** @psalm-suppress UndefinedConstant */
            $row = oci_fetch_array($sql, OCI_RETURN_NULLS);
            if ($row) {
                $scalar = $row[0];
            } else {
                $scalar = false;
            }

            oci_free_cursor($sql);

            return $scalar;
        }

        if (is_string($sql)) {
            $sql = new SqlStatement($sql);
        } elseif (!($sql instanceof SqlStatement)) {
            throw new InvalidArgumentException("The SQL must be a cursor, string or a SqlStatement object");
        }

        return $sql->getScalar($this, $array);
    }

    /**
     * @param string $tablename
     * @return array
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     */
    #[Override]
    public function getAllFields(string $tablename): array
    {
        $cur = $this->prepareStatement(SqlHelper::createSafeSQL("select * from :table", array(':table' => $tablename)));
        $this->executeCursor($cur);

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
     * @param mixed $sql
     * @param array|null $array
     * @return bool
     * @throws DatabaseException
     */
    #[Override]
    public function execute(mixed $sql, ?array $array = null): bool
    {
        if (is_resource($sql)) {
            oci_free_cursor($sql);
            return true;
        }

        if (is_string($sql)) {
            $sql = new SqlStatement($sql);
        } elseif (!($sql instanceof SqlStatement)) {
            throw new InvalidArgumentException("The SQL must be a cursor, string or a SqlStatement object");
        }

        $sql->execute($this, $array);
        return true;

    }

    /**
     *
     * @return resource|false
     */
    #[Override]
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
    #[Override]
    public function executeAndGetId(string $sql, ?array $array = null): mixed
    {
        return $this->getDbHelper()->executeAndGetInsertedId($this, $sql, $array);
    }

    /**
     * @return DbFunctionsInterface
     */
    #[Override]
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
    #[Override]
    public function getUri(): Uri
    {
        return $this->connectionUri;
    }

    /**
     * @throws NotImplementedException
     */
    #[Override]
    public function isSupportMultiRowset(): bool
    {
        return false;
    }

    /**
     * @param bool $multipleRowSet
     * @throws NotImplementedException
     */
    #[Override]
    public function setSupportMultiRowset(bool $multipleRowSet): void
    {
        throw new NotImplementedException('Method not implemented for OCI Driver');
    }

    #[Override]
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

    #[Override]
    public function disconnect(): void
    {
        $this->conn = null;
    }

    #[Override]
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

    #[Override]
    public function enableLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    #[Override]
    public function log(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }
}