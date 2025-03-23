<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\Helpers\SqlBind;
use ByJG\AnyDataset\Db\Helpers\SqlHelper;
use ByJG\AnyDataset\Db\Traits\DatabaseExecutorTrait;
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

abstract class DbPdoDriver implements DbDriverInterface
{
    use DbCacheTrait;
    use TransactionTrait;
    use DatabaseExecutorTrait;

    protected ?PDO $instance = null;

    protected bool $supportMultiRowset = false;

    const DONT_PARSE_PARAM = "dont_parse_param";
    const UNIX_SOCKET = "unix_socket";

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
     * @throws DbDriverNotConnected
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
        $this->instance = $this->pdoObj->createInstance($this->preOptions, $this->postOptions, $this->executeAfterConnect);

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
        if (!$this->getUri()->hasQueryKey(self::DONT_PARSE_PARAM)) {
            list($sql, $params) = SqlBind::parseSQL($this->pdoObj->getUri(), $sql, $params);
        }

        if (($cacheInfo['sql'] ?? "") != $sql || empty($cacheInfo['stmt'])) {
            $stmt = $this->getInstance()->prepare($sql);
        } else {
            $stmt = $cacheInfo['stmt'];
        }

        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $stmt->bindValue(":" . SqlBind::keyAdj($key), $value);
            }
        }

        $this->logger->debug("SQL: $sql\nParams: " . json_encode($params));

        $cacheInfo['sql'] = $sql;
        $cacheInfo['stmt'] = $stmt;

        return $stmt;
    }

    #[Override]
    public function executeCursor(mixed $statement): void
    {
        $statement->execute();
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

        throw new InvalidArgumentException('Invalid statement type');
    }

    /**
     * Get an iterator for the provided SQL or execute an existing PDOStatement.
     *
     * @param string|SqlStatement $sql PDOStatement, string SQL, or SqlStatement object
     * @param array|null $params Parameters if $sql is a string
     * @param int $preFetch Number of rows to prefetch
     * @param string|null $entityClass Optional entity class name to return rows as objects
     * @param PropertyHandlerInterface|null $entityTransformer Optional transformation handler for customizing entity mapping
     * @return GenericDbIterator|GenericIterator The iterator for the query results
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws DatabaseException
     * @throws XmlUtilException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    #[Override]
    public function getIterator(string|SqlStatement $sql, ?array $params = null, int $preFetch = 0, ?string $entityClass = null, ?PropertyHandlerInterface $entityTransformer = null): GenericDbIterator|GenericIterator
    {
        // Use the DatabaseExecutorTrait to handle all types of statements
        return $this->executeStatement($sql, $params, $preFetch, $entityClass, $entityTransformer);
    }

    #[Override]
    public function getScalar(mixed $sql, ?array $array = null): mixed
    {
        if ($sql instanceof PDOStatement) {
            $scalar = $sql->fetchColumn();
            $sql->closeCursor();
            return $scalar;
        }

        if (is_string($sql)) {
            $sql = new SqlStatement($sql);
        } elseif (!($sql instanceof SqlStatement)) {
            throw new InvalidArgumentException("The SQL must be a cursor, string or a SqlStatement object");
        }

        // Use parameters from SqlStatement if no parameters are provided
        $params = $array ?? $sql->getParams();

        // Execute the scalar query
        $statement = $this->prepareStatement($sql->getSql(), $params);
        $this->executeCursor($statement);
        return $statement->fetchColumn();
    }

    #[Override]
    public function getAllFields(string $tablename): array
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


    #[Override]
    public function execute(mixed $sql, ?array $array = null): bool
    {
        if ($sql instanceof PDOStatement) {
            if ($this->isSupportMultiRowset()) {
                // Check error
                do {
                    // This loop is only to throw an error (if exists)
                    // in case of execute multiple queries
                } while ($sql->nextRowset());
            }

            return true;
        }

        if (is_string($sql)) {
            $sql = new SqlStatement($sql);
        } elseif (!($sql instanceof SqlStatement)) {
            throw new InvalidArgumentException("The SQL must be a cursor, string or a SqlStatement object");
        }

        // Use parameters from SqlStatement if no parameters are provided
        $params = $array ?? $sql->getParams();

        // Execute the statement directly
        $statement = $this->prepareStatement($sql->getSql(), $params);
        $this->executeCursor($statement);
        return true;
    }

    #[Override]
    public function executeAndGetId(string|SqlStatement $sql, ?array $array = null): mixed
    {
        if ($sql instanceof SqlStatement) {
            $params = $array ?? $sql->getParams();
            return $this->getDbHelper()->executeAndGetInsertedId($this, $sql->getSql(), $params);
        }
        
        return $this->getDbHelper()->executeAndGetInsertedId($this, $sql, $array);
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

    protected ?DbFunctionsInterface $dbHelper = null;

    #[Override]
    public function getDbHelper(): DbFunctionsInterface
    {
        if (empty($this->dbHelper)) {
            $this->dbHelper = Factory::getDbFunctions($this->pdoObj->getUri());
        }
        return $this->dbHelper;
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

    protected function getInstance(): ?PDO
    {
        $this->isConnected(true, true);
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

    protected function transactionHandler(TransactionStageEnum $action, string $isoLevelCommand = ""): void
    {
        switch ($action) {
            case TransactionStageEnum::begin:
                if (!empty($isoLevelCommand)) {
                    $this->getInstance()->exec($isoLevelCommand);
                }
                $this->getInstance()->beginTransaction();
                break;
            case TransactionStageEnum::commit:
                $this->getInstance()->commit();
                break;
            case TransactionStageEnum::rollback:
                $this->getInstance()->rollBack();
                break;
        }
    }
}
