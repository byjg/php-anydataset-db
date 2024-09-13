<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\Helpers\SqlBind;
use ByJG\AnyDataset\Db\Helpers\SqlHelper;
use ByJG\AnyDataset\Db\Traits\DbCacheTrait;
use ByJG\AnyDataset\Db\Traits\TransactionTrait;
use ByJG\Util\Uri;
use DateInterval;
use Exception;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

abstract class DbPdoDriver implements DbDriverInterface
{
    use TransactionTrait;
    use DbCacheTrait;

    protected ?PDO $instance = null;

    protected bool $supportMultiRowset = false;



    const DONT_PARSE_PARAM = "dont_parse_param";
    const STATEMENT_CACHE = "stmtcache";
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

    public function disconnect(): void
    {
        $this->clearCache();
        $this->instance = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     *
     * @param string $sql
     * @param array|null $array $array
     * @return PDOStatement
     * @throws DbDriverNotConnected
     */
    protected function getDBStatement(string $sql, ?array $array = null): PDOStatement
    {
        if (!$this->getUri()->hasQueryKey(self::DONT_PARSE_PARAM)) {
            list($sql, $array) = SqlBind::parseSQL($this->pdoObj->getUri(), $sql, $array);
        }

        if ($this->pdoObj->expectToCacheResults()) {
            $this->isConnected(true, true);
            $stmt = $this->getOrSetSqlCacheStmt($sql);
        } else {
            $stmt = $this->getInstance()->prepare($sql);
        }

        if (!empty($array)) {
            foreach ($array as $key => $value) {
                $stmt->bindValue(":" . SqlBind::keyAdj($key), $value);
            }
        }

        $this->logger->debug("SQL: $sql\nParams: " . json_encode($array));

        return $stmt;
    }

    public function getIterator(string $sql, ?array $params = null, ?CacheInterface $cache = null, DateInterval|int $ttl = 60): GenericIterator
    {
        return $this->getIteratorUsingCache($sql, $params, $cache, $ttl, function ($sql, $params) {
            $stmt = $this->getDBStatement($sql, $params);
            $stmt->execute();
            return new DbIterator($stmt);
        });
    }

    public function getScalar(string $sql, ?array $array = null): mixed
    {
        $stmt = $this->getDBStatement($sql, $array);
        $stmt->execute();

        $scalar = $stmt->fetchColumn();

        $stmt->closeCursor();

        return $scalar;
    }

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


    public function execute(string $sql, ?array $array = null): bool
    {
        $stmt = $this->getDBStatement($sql, $array);
        $result = $stmt->execute();

        if ($this->isSupportMultiRowset()) {
            // Check error
            do {
                // This loop is only to throw an error (if exists)
                // in case of execute multiple queries
            } while ($stmt->nextRowset());
        }

        return $result;
    }

    public function executeAndGetId(string $sql, ?array $array = null): mixed
    {
        return $this->getDbHelper()->executeAndGetInsertedId($this, $sql, $array);
    }

    /**
     *
     * @return PDO|null
     */
    public function getDbConnection(): ?PDO
    {
        return $this->instance;
    }

    public function getAttribute(string $name): mixed
    {
        return $this->getInstance()->getAttribute($name);
    }

    public function setAttribute(string $name, mixed $value): void
    {
        $this->getInstance()->setAttribute($name, $value);
    }

    protected ?DbFunctionsInterface $dbHelper = null;

    public function getDbHelper(): DbFunctionsInterface
    {
        if (empty($this->dbHelper)) {
            $this->dbHelper = Factory::getDbFunctions($this->pdoObj->getUri());
        }
        return $this->dbHelper;
    }

    public function getUri(): Uri
    {
        return $this->pdoObj->getUri();
    }

    /**
     * @return bool
     */
    public function isSupportMultiRowset(): bool
    {
        return $this->supportMultiRowset;
    }

    /**
     * @param bool $multipleRowSet
     */
    public function setSupportMultiRowset(bool $multipleRowSet): void
    {
        $this->supportMultiRowset = $multipleRowSet;
    }


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

    public function enableLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

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
