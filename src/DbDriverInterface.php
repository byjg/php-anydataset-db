<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Interfaces\DbCacheInterface;
use ByJG\AnyDataset\Db\Interfaces\DbTransactionInterface;
use ByJG\Util\Uri;
use DateInterval;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

interface DbDriverInterface extends DbTransactionInterface, DbCacheInterface
{

    public static function schema();

    /**
     * @param string $sql
     * @param array|null $params
     * @param CacheInterface|null $cache
     * @param int|DateInterval $ttl
     * @return GenericIterator
     */
    public function getIterator(string $sql, ?array $params = null, ?CacheInterface $cache = null, DateInterval|int $ttl = 60): GenericIterator;

    public function getScalar(string $sql, ?array $array = null): mixed;

    public function getAllFields(string $tablename): array;

    public function execute(string $sql, ?array $array = null): bool;

    public function executeAndGetId(string $sql, ?array $array = null): mixed;

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
