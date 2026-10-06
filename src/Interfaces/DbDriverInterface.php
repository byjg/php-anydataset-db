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
     * Returns the column names of an executed statement, in lower case and in the order
     * the database returns them. It must work for a statement that produced no rows.
     *
     * @param mixed $statement The executed statement (PDOStatement, resource, etc.)
     * @return string[]
     */
    public function getStatementFields(mixed $statement): array;

    /**
     * @return SqlDialectInterface
     */
    /**
     * Get the class name of the DbFunctionsInterface implementation for this driver
     *
     * @return class-string<SqlDialectInterface> Fully qualified class name of the DbFunctionsInterface implementation
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
