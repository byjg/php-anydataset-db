<?php

namespace ByJG\AnyDataset\Db\Interfaces;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\GenericDbIterator;
use ByJG\AnyDataset\Db\SqlStatement;
use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;
use ByJG\Util\Uri;
use Psr\Log\LoggerInterface;

interface DbDriverInterface extends DbTransactionInterface
{

    public static function schema();

    public function prepareStatement(string $sql, ?array $params = null, ?array &$cacheInfo = []): mixed;

    public function executeCursor(mixed $statement): void;

    public function processMultiRowset(mixed $statement): void;

    /**
     * Execute a SQL statement and return an iterator over the results
     *
     * @param string|SqlStatement $sql
     * @param array|null $params
     * @param int $preFetch
     * @return GenericDbIterator|GenericIterator
     *@deprecated Use DatabaseExecutor::using($driver)->getIterator() instead. This method will be removed in version 7.0.
     */
    public function getIterator(string|SqlStatement $sql, ?array $params = null, int $preFetch = 0): GenericDbIterator|GenericIterator;

    /**
     * Execute a SQL statement and return a single scalar value
     *
     * @param string|SqlStatement $sql
     * @param array|null $array
     * @return mixed
     * @deprecated Use DatabaseExecutor::using($driver)->getScalar() instead. This method will be removed in version 7.0.
     */
    public function getScalar(string|SqlStatement $sql, ?array $array = null): mixed;

    /**
     * Get all field names from a table
     *
     * @param string $tablename
     * @return array
     * @deprecated Use DatabaseExecutor::using($driver)->getAllFields() instead. This method will be removed in version 7.0.
     */
    public function getAllFields(string $tablename): array;

    /**
     * Execute a SQL statement without returning results
     *
     * @param string|SqlStatement $sql
     * @param array|null $array
     * @return bool
     * @deprecated Use DatabaseExecutor::using($driver)->execute() instead. This method will be removed in version 7.0.
     */
    public function execute(string|SqlStatement $sql, ?array $array = null): bool;

    /**
     * Execute a SQL INSERT statement and return the generated ID
     *
     * @param string|SqlStatement $sql
     * @param array|null $array
     * @return mixed
     * @deprecated Use DatabaseExecutor::using($driver)->executeAndGetId() instead. This method will be removed in version 7.0.
     */
    public function executeAndGetId(string|SqlStatement $sql, ?array $array = null): mixed;

    /**
     * Creates a database driver-specific iterator for query results
     *
     * @param mixed $statement The statement to create an iterator from (PDOStatement, resource, etc.)
     * @param int $preFetch Number of rows to prefetch
     * @param string|null $entityClass Optional entity class name to return rows as objects
     * @param PropertyHandlerInterface|null $entityTransformer Optional transformation function for customizing entity mapping
     * @return GenericDbIterator|GenericIterator The driver-specific iterator for the query results
     */
    public function getDriverIterator(mixed $statement, int $preFetch = 0, ?string $entityClass = null, ?PropertyHandlerInterface $entityTransformer = null): GenericDbIterator|GenericIterator;

    /**
     * @return SqlDialectInterface
     */
    /**
     * Get the class name of the DbFunctionsInterface implementation for this driver
     *
     * @return string Fully qualified class name of the DbFunctionsInterface implementation
     */
    public function getSqlDialectClass(): string;

    public function getSqlDialect(): SqlDialectInterface;

    /**
     * @return mixed
     */
    public function getDbConnection(): mixed;

    /**
     * @return Uri
     */
    public function getUri(): Uri;

    public function isSupportMultiRowset(): bool;

    public function setSupportMultiRowset(bool $multipleRowSet): void;

    public function isConnected(bool $softCheck = false, bool $throwError = false): bool;
    public function reconnect(bool $force = false): bool;

    public function disconnect(): void;

    public function enableLogger(LoggerInterface $logger): void;

    public function log(string $message, array $context = []): void;

}
