<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\Util\Uri;
use PDO;
use Psr\Log\LoggerInterface;

interface DbDriverInterface
{

    public static function schema();

    /**
     * @param string $sql
     * @param array|null $params
     * @return GenericIterator
     */
    public function getIterator($sql, $params = null);

    public function getScalar($sql, $array = null);

    public function getAllFields($tablename);

    public function execute($sql, $array = null);

    public function executeAndGetId($sql, $array = null);

    /**
     * @return DbFunctionsInterface
     */
    public function getDbHelper();

    public function beginTransaction(IsolationLevelEnum $isolationLevel = null);

    public function commitTransaction();

    public function rollbackTransaction();

    public function hasActiveTransaction(): bool;

    public function requiresTransaction();

    /**
     * @return PDO
     */
    public function getDbConnection();

    /**
     * @return Uri
     */
    public function getUri();

    public function setAttribute($name, $value);

    public function getAttribute($name);

    public function isSupportMultRowset();

    public function setSupportMultRowset($multipleRowSet);

    public function getMaxStmtCache();

    public function getCountStmtCache();

    public function isConnected($softCheck = false, $throwError = false);
    public function reconnect($force = false);

    public function disconnect();

    /**
     * @param int $maxStmtCache
     */
    public function setMaxStmtCache($maxStmtCache);

    public function enableLogger(LoggerInterface $logger);

    public function log($message, $context = []);

}
