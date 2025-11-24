<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\Interfaces\DbDriverInterface;
use ByJG\AnyDataset\Db\Interfaces\SqlDialectInterface;
use ByJG\AnyDataset\Db\Traits\DbCacheTrait;
use ByJG\AnyDataset\Db\Traits\TransactionTrait;
use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;
use ByJG\Util\Uri;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use Exception;
use InvalidArgumentException;
use Override;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

abstract class DbPdoDriver implements DbDriverInterface
{
    use DbCacheTrait;
    use TransactionTrait;

    protected ?PDO $instance = null;

    protected bool $supportMultiRowset = false;

    const string DONT_PARSE_PARAM = "dont_parse_param";
    const string UNIX_SOCKET = "unix_socket";

    protected PdoObj $pdoObj;

    protected ?array $preOptions;

    protected ?array $postOptions;

    protected ?array $executeAfterConnect;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * DbPdoDriver constructor.
     *
     * @param Uri $connUri
     * @param array|null $preOptions
     * @param array|null $postOptions
     * @param array $executeAfterConnect
     * @throws DbDriverNotConnected|NotAvailableException
     */
    public function __construct(Uri $connUri, ?array $preOptions = null, ?array $postOptions = null, array $executeAfterConnect = [])
    {
        $this->logger = new NullLogger();
        $this->pdoObj = new PdoObj($connUri);
        $this->preOptions = $preOptions;
        $this->postOptions = $postOptions;
        $this->executeAfterConnect = $executeAfterConnect;
        $this->reconnect();
    }

    /**
     * @throws DbDriverNotConnected
     */
    #[Override]
    public function reconnect(bool $force = false): bool
    {
        if ($this->isConnected() && !$force) {
            return false;
        }

        // Release old instance
        $this->disconnect();

        // Connect
        $this->instance = $this->pdoObj->createInstance($this->preOptions, $this->postOptions, $this->executeAfterConnect ?? []);

        return true;
    }

    #[Override]
    public function disconnect(): void
    {
        $this->instance = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     *
     * @param string $sql
     * @param array|null $params
     * @param array|null &$cacheInfo
     * @return PDOStatement
     * @throws DbDriverNotConnected
     */
    #[Override]
    public function prepareStatement(string $sql, ?array $params = null, ?array &$cacheInfo = []): PDOStatement
    {
        if (is_null($this->getInstance())) {
            throw new RuntimeException('Database connection is not established');
        }

        if (!$this->getUri()->hasQueryKey(self::DONT_PARSE_PARAM)) {
            list($sql, $params) = ParameterBinder::prepareParameterBindings($this->pdoObj->getUri(), $sql, $params);
        }

        if (($cacheInfo['sql'] ?? "") != $sql || empty($cacheInfo['stmt'])) {
            $stmt = $this->getInstance()->prepare($sql);
        } else {
            $stmt = $cacheInfo['stmt'];
        }

        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $stmt->bindValue(":" . ParameterBinder::sanitizeParameterKey($key), $value);
            }
        }

        $this->logger->debug("SQL: $sql\nParams: " . (json_encode($params ?? []) ?: '[]'));

        $cacheInfo['sql'] = $sql;
        $cacheInfo['stmt'] = $stmt;

        return $stmt;
    }

    #[Override]
    public function executeCursor(mixed $statement): void
    {
        if (!($statement instanceof PDOStatement)) {
            throw new InvalidArgumentException("The statement parameter must be a PDOStatement object");
        }

        $statement->execute();
    }

    #[Override]
    public function processMultiRowset(mixed $statement): void
    {
        if (!($statement instanceof PDOStatement)) {
            throw new InvalidArgumentException("The statement parameter must be a PDOStatement object");
        }

        if ($this->isSupportMultiRowset()) {
            // Advance through rowsets to surface any errors from later statements
            // This loop intentionally does nothing but advance - any errors will be thrown automatically
            while ($statement->nextRowset()) {
                // Intentionally empty - just consuming rowsets to trigger any errors
            }
        }
    }

    /**
     * Handles PDOStatement specific statement type
     *
     * @param mixed $statement The statement to check and handle
     * @param int $preFetch Number of rows to prefetch
     * @param string|null $entityClass Optional entity class name to return rows as objects
     * @param PropertyHandlerInterface|null $entityTransformer Optional transformation function for customizing entity mapping
     * @return GenericDbIterator|GenericIterator Returns GenericIterator for the statement
     */
    #[Override]
    public function getDriverIterator(mixed $statement, int $preFetch = 0, ?string $entityClass = null, ?PropertyHandlerInterface $entityTransformer = null): GenericDbIterator|GenericIterator
    {
        if ($statement instanceof PDOStatement) {
            return new DbIterator($statement, $preFetch, $entityClass, $entityTransformer);
        }

        throw new InvalidArgumentException('The argument needs to be a PDOStatement object');
    }

    /**
     * Get an iterator for the provided SQL or execute an existing PDOStatement.
     *
     * @param string|SqlStatement $sql PDOStatement, string SQL, or SqlStatement object
     * @param array|null $params Parameters if $sql is a string
     * @param int $preFetch Number of rows to prefetch
     * @return GenericDbIterator|GenericIterator The iterator for the query results
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws DatabaseException
     * @throws XmlUtilException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *@deprecated Use DatabaseExecutor::using($driver)->getIterator() instead. This method will be removed in version 7.0.
     */
    #[Override]
    public function getIterator(string|SqlStatement $sql, ?array $params = null, int $preFetch = 0): GenericDbIterator|GenericIterator
    {
        return DatabaseExecutor::using($this)->getIterator($sql, $params, $preFetch);
    }

    /**
     * @deprecated Use DatabaseExecutor::using($driver)->getScalar() instead. This method will be removed in version 7.0.
     */
    #[Override]
    public function getScalar(string|SqlStatement $sql, ?array $array = null): mixed
    {
        return DatabaseExecutor::using($this)->getScalar($sql, $array);
    }

    /**
     * @deprecated Use DatabaseExecutor::using($driver)->getAllFields() instead. This method will be removed in version 7.0.
     */
    #[Override]
    public function getAllFields(string $tablename): array
    {
        return DatabaseExecutor::using($this)->getAllFields($tablename);
    }

    /**
     * @deprecated Use DatabaseExecutor::using($driver)->execute() instead. This method will be removed in version 7.0.
     */
    #[Override]
    public function execute(string|SqlStatement $sql, ?array $array = null): bool
    {
        return DatabaseExecutor::using($this)->execute($sql, $array);
    }

    /**
     * @deprecated Use DatabaseExecutor::using($driver)->executeAndGetId() instead. This method will be removed in version 7.0.
     */
    #[Override]
    public function executeAndGetId(string|SqlStatement $sql, ?array $array = null): mixed
    {
        return DatabaseExecutor::using($this)->executeAndGetId($sql, $array);
    }

    /**
     *
     * @return PDO|null
     */
    #[Override]
    public function getDbConnection(): ?PDO
    {
        return $this->instance;
    }

    protected ?SqlDialectInterface $sqlDialect = null;

    #[Override]
    public function getSqlDialect(): SqlDialectInterface
    {
        if (empty($this->sqlDialect)) {
            $helperClass = $this->getSqlDialectClass();
            $this->sqlDialect = new $helperClass();
        }
        return $this->sqlDialect;
    }

    #[Override]
    public function getUri(): Uri
    {
        return $this->pdoObj->getUri();
    }

    /**
     * @return bool
     */
    #[Override]
    public function isSupportMultiRowset(): bool
    {
        return $this->supportMultiRowset;
    }

    /**
     * @param bool $multipleRowSet
     */
    #[Override]
    public function setSupportMultiRowset(bool $multipleRowSet): void
    {
        $this->supportMultiRowset = $multipleRowSet;
    }


    /**
     * @throws DbDriverNotConnected
     */
    #[Override]
    public function isConnected(bool $softCheck = false, bool $throwError = false): bool
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

    /**
     * @throws DbDriverNotConnected
     */
    protected function getInstance(): PDO
    {
        $this->isConnected(true, true);
        if ($this->instance === null) {
            throw new DbDriverNotConnected('PDO instance is null');
        }
        return $this->instance;
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

    /**
     * @throws DbDriverNotConnected
     */
    protected function transactionHandler(TransactionStageEnum $action, string $isoLevelCommand = ""): void
    {
        $instance = $this->getInstance();

        switch ($action) {
            case TransactionStageEnum::begin:
                if (!empty($isoLevelCommand)) {
                    $instance->exec($isoLevelCommand);
                }
                $instance->beginTransaction();
                break;
            case TransactionStageEnum::commit:
                $instance->commit();
                break;
            case TransactionStageEnum::rollback:
                $instance->rollBack();
                break;
        }
    }
}
