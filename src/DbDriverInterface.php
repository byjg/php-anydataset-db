<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Interfaces\DbTransactionInterface;
use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;
use ByJG\Util\Uri;
use Psr\Log\LoggerInterface;

interface DbDriverInterface extends DbTransactionInterface
{

    public static function schema();

    public function prepareStatement(string $sql, ?array $params = null, ?array &$cacheInfo = []): mixed;

    public function executeCursor(mixed $statement): void;

    /**
     * @param string|SqlStatement $sql
     * @param array|null $params
     * @param int $preFetch
     * @return GenericDbIterator|GenericIterator
     */
    public function getIterator(string|SqlStatement $sql, ?array $params = null, int $preFetch = 0): GenericDbIterator|GenericIterator;

    public function getScalar(string|SqlStatement $sql, ?array $array = null): mixed;

    public function getAllFields(string $tablename): array;

    public function execute(string|SqlStatement $sql, ?array $array = null): bool;

    /**
     * @param string|SqlStatement $sql
     * @param array|null $array
     * @return mixed
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
     * @return DbFunctionsInterface
     */
    public function getDbHelper(): DbFunctionsInterface;

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
