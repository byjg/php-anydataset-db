<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Interfaces\DbCacheInterface;
use ByJG\AnyDataset\Db\Interfaces\DbTransactionInterface;
use ByJG\Util\Uri;
use PDO;
use Psr\Log\LoggerInterface;

interface DbDriverInterface extends DbTransactionInterface, DbCacheInterface
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

    public function isConnected($softCheck = false, $throwError = false);
    public function reconnect($force = false);

    public function disconnect();

    public function enableLogger(LoggerInterface $logger);

    public function log($message, $context = []);

}
