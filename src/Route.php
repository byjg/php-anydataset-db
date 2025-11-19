<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Exception\RouteNotFoundException;
use ByJG\AnyDataset\Db\Exception\RouteNotInitializedException;
use ByJG\AnyDataset\Db\Exception\RouteNotMatchedException;
use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;
use ByJG\Util\Uri;
use InvalidArgumentException;
use Override;
use Psr\Log\LoggerInterface;

class Route implements DbDriverInterface
{
    #[Override]
    public static function schema()
    {
        return null;
    }

    /**
     * @var array<string, DbDriverInterface[]>
     */
    protected array $drivers = [];

    /**
     * @var string[]
     */
    protected array $routes;

    /**
     * @var DbDriverInterface|null Last matched driver for transaction and connection operations
     */
    protected ?DbDriverInterface $lastMatchedDriver = null;

    /**
     * Route constructor.
     */
    public function __construct()
    {
    }

    //<editor-fold desc="Route Methods">

    /**
     * Add one or more database drivers to a route
     *
     * @param string $routeName The name of the route
     * @param DbDriverInterface|DbDriverInterface[] $driver Single driver or array of drivers
     * @return $this
     */
    public function addDriver(string $routeName, DbDriverInterface|array $driver): static
    {
        if (!isset($this->drivers[$routeName])) {
            $this->drivers[$routeName] = [];
        }

        if (!is_array($driver)) {
            $driver = [$driver];
        }

        foreach ($driver as $item) {
            if (!($item instanceof DbDriverInterface)) {
                throw new InvalidArgumentException('All items must be DbDriverInterface instances');
            }
            $this->drivers[$routeName][] = $item;
        }

        return $this;
    }

    /**
     * @param string $routeName
     * @param string|null $table
     * @return static
     * @throws RouteNotFoundException
     */
    public function addRouteForSelect(string $routeName, ?string $table = null): static
    {
        if (empty($table)) {
            // Match any SELECT query, with or without FROM clause
            return $this->addCustomRoute($routeName, '^select\s');
        }
        // Match SELECT with specific table
        return $this->addCustomRoute($routeName, '^select.*from\s+([`]?' . $table . '[`]?)\s');
    }

    /**
     * @param string $routeName
     * @param string|null $table
     * @return static
     * @throws RouteNotFoundException
     */
    public function addRouteForInsert(string $routeName, ?string $table = null): static
    {
        if (empty($table)) {
            $table = '\w+';
        }
        return $this->addCustomRoute($routeName, '^insert\s+into\s+([`]?' . $table . '[`]?)\s+\(');
    }

    /**
     * @param string $routeName
     * @param string|null $table
     * @return static
     * @throws RouteNotFoundException
     */
    public function addRouteForUpdate(string $routeName, ?string $table = null): static
    {
        if (empty($table)) {
            $table = '\w+';
        }
        return $this->addCustomRoute($routeName, '^update\s+([`]?' . $table . '[`]?)\s+set');
    }

    /**
     * @param string $routeName
     * @param string|null $table
     * @return static
     * @throws RouteNotFoundException
     */
    public function addRouteForDelete(string $routeName, ?string $table = null): static
    {
        if (empty($table)) {
            $table = '\w+';
        }
        return $this->addCustomRoute($routeName, '^delete\s+(from\s+)?([`]?' . $table . '[`]?)\s');
    }

    /**
     * @param string $routeName
     * @param string|null $table
     * @return static
     * @throws RouteNotFoundException
     */
    public function addRouteForTable(string $routeName, ?string $table = null): static
    {
        $this->addRouteForRead($routeName, $table);
        $this->addRouteForWrite($routeName, $table);
        return $this;
    }

    /**
     * @param string $routeName
     * @param string|null $table
     * @return static
     * @throws RouteNotFoundException
     */
    public function addRouteForWrite(string $routeName, ?string $table = null): static
    {
        $this->addRouteForInsert($routeName, $table);
        $this->addRouteForUpdate($routeName, $table);
        $this->addRouteForDelete($routeName, $table);
        return $this;
    }

    /**
     * @param string $routeName
     * @param string|null $table
     * @return static
     * @throws RouteNotFoundException
     */
    public function addRouteForRead(string $routeName, ?string $table = null): static
    {
        return $this->addRouteForSelect($routeName, $table);
    }

    /**
     * @param string $routeName
     * @param string $field
     * @param string $value
     * @return static
     * @throws RouteNotFoundException
     */
    public function addRouteForFilter(string $routeName, string $field, string $value): static
    {
        return $this->addCustomRoute($routeName, "\\s`?$field`?\\s*=\\s*'?$value'?\s");
    }

    /**
     * @param string $routeName
     * @return static
     * @throws RouteNotFoundException
     */
    public function addDefaultRoute(string $routeName): static
    {
        return $this->addCustomRoute($routeName, '.');
    }

    /**
     * @param string $routeName
     * @param string $regEx
     * @return static
     * @throws RouteNotFoundException
     */
    public function addCustomRoute(string $routeName, string $regEx): static
    {
        if (!isset($this->drivers[$routeName])) {
            throw new RouteNotFoundException("Invalid route $routeName");
        }
        $this->routes[$regEx] = $routeName;
        return $this;
    }

    /**
     * Match a SQL query to a route and return the corresponding driver
     *
     * @param string $sql The SQL query to match
     * @return DbDriverInterface The matched database driver
     * @throws RouteNotMatchedException If no route matches the SQL query
     */
    public function matchRoute(string $sql): DbDriverInterface
    {
        $sql = trim(strtolower(str_replace("\n", " ", $sql))) . ' ';
        foreach ($this->routes as $pattern => $routeName) {
            if (!preg_match("/$pattern/", $sql)) {
                continue;
            }

            // Only use rand() if there are multiple drivers (load balancing)
            $driverCount = count($this->drivers[$routeName]);
            $item = $driverCount > 1 ? rand(0, $driverCount - 1) : 0;
            $driver = $this->drivers[$routeName][$item];

            // Track last matched driver
            $this->lastMatchedDriver = $driver;

            return $driver;
        }

        throw new RouteNotMatchedException('Route not matched');
    }
    //</editor-fold>

    //<editor-fold desc="DbDriverInterface">

    #[Override]
    public function prepareStatement(string $sql, ?array $params = null, ?array &$cacheInfo = []): mixed
    {
        // Detect system queries (SELECT without FROM) and use last matched driver
        // This handles cases like SELECT LAST_INSERT_ID() after an INSERT
        $sqlLower = strtolower(trim($sql));
        $isSystemQuery = preg_match('/^select\s+(?!.*\bfrom\b)/', $sqlLower);

        if ($isSystemQuery && $this->lastMatchedDriver !== null) {
            $driver = $this->lastMatchedDriver;
        } else {
            try {
                $driver = $this->matchRoute($sql);
            } catch (RouteNotMatchedException $e) {
                // If no route matches and we have a last matched driver, use that as fallback
                if ($this->lastMatchedDriver !== null) {
                    $driver = $this->lastMatchedDriver;
                } else {
                    throw $e;
                }
            }
        }
        return $driver->prepareStatement($sql, $params, $cacheInfo);
    }

    #[Override]
    public function executeCursor(mixed $statement): void
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot execute cursor: no database has been selected. Call prepareStatement first.');
        }
        $this->lastMatchedDriver->executeCursor($statement);
    }

    /**
     * @param string|SqlStatement $sql
     * @param array|null $params
     * @param int $preFetch
     * @return GenericDbIterator|GenericIterator
     * @throws RouteNotMatchedException
     * @deprecated Use DatabaseExecutor::using($route)->getIterator() instead. This method will be removed in version 7.0.
     */
    #[Override]
    public function getIterator(string|SqlStatement $sql, ?array $params = null, int $preFetch = 0): GenericDbIterator|GenericIterator
    {
        $sqlString = $sql instanceof SqlStatement ? $sql->getSql() : $sql;
        $driver = $this->matchRoute($sqlString);
        return $driver->getIterator($sql, $params, $preFetch);
    }

    /**
     * @param string|SqlStatement $sql
     * @param array|null $array
     * @return mixed
     * @throws RouteNotMatchedException
     * @deprecated Use DatabaseExecutor::using($route)->getScalar() instead. This method will be removed in version 7.0.
     */
    #[Override]
    public function getScalar(string|SqlStatement $sql, ?array $array = null): mixed
    {
        $sqlString = $sql instanceof SqlStatement ? $sql->getSql() : $sql;
        $driver = $this->matchRoute($sqlString);
        return $driver->getScalar($sql, $array);
    }

    /**
     * @param string $tablename
     * @return array
     * @throws RouteNotMatchedException
     * @deprecated Use DatabaseExecutor::using($route)->getAllFields() instead. This method will be removed in version 7.0.
     */
    #[Override]
    public function getAllFields(string $tablename): array
    {
        // Use a simple SELECT query to match the route
        $sql = "SELECT * FROM $tablename LIMIT 1";
        $driver = $this->matchRoute($sql);
        return $driver->getAllFields($tablename);
    }

    /**
     * @param string|SqlStatement $sql
     * @param array|null $array
     * @return bool
     * @throws RouteNotMatchedException
     * @deprecated Use DatabaseExecutor::using($route)->execute() instead. This method will be removed in version 7.0.
     */
    #[Override]
    public function execute(string|SqlStatement $sql, ?array $array = null): bool
    {
        $sqlString = $sql instanceof SqlStatement ? $sql->getSql() : $sql;
        $driver = $this->matchRoute($sqlString);
        return $driver->execute($sql, $array);
    }

    /**
     * @param IsolationLevelEnum|null $isolationLevel
     * @param bool $allowJoin
     * @return void
     * @throws RouteNotInitializedException If no driver has been matched yet
     */
    #[Override]
    public function beginTransaction(?IsolationLevelEnum $isolationLevel = null, bool $allowJoin = false): void
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot begin transaction: no database has been selected. Execute a query first to match a route.');
        }
        $this->lastMatchedDriver->beginTransaction($isolationLevel, $allowJoin);
    }

    /**
     * @return void
     * @throws RouteNotInitializedException If no driver has been matched yet
     */
    #[Override]
    public function commitTransaction(): void
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot commit transaction: no database has been selected. Execute a query first to match a route.');
        }
        $this->lastMatchedDriver->commitTransaction();
    }

    /**
     * @return void
     * @throws RouteNotInitializedException If no driver has been matched yet
     */
    #[Override]
    public function rollbackTransaction(): void
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot rollback transaction: no database has been selected. Execute a query first to match a route.');
        }
        $this->lastMatchedDriver->rollbackTransaction();
    }

    /**
     * @return mixed
     * @throws RouteNotInitializedException If no driver has been matched yet
     */
    #[Override]
    public function getDbConnection(): mixed
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot get connection: no database has been selected. Execute a query first to match a route.');
        }
        return $this->lastMatchedDriver->getDbConnection();
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     * @throws RouteNotInitializedException If no driver has been matched yet
     */
    public function setAttribute(string $name, mixed $value): void
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot set attribute: no database has been selected. Execute a query first to match a route.');
        }
        $this->lastMatchedDriver->setAttribute($name, $value);
    }

    /**
     * @param string $name
     * @return mixed
     * @throws RouteNotInitializedException If no driver has been matched yet
     */
    public function getAttribute(string $name): mixed
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot get attribute: no database has been selected. Execute a query first to match a route.');
        }
        return $this->lastMatchedDriver->getAttribute($name);
    }

    /**
     * @param string|SqlStatement $sql
     * @param array|null $array
     * @return mixed
     * @throws RouteNotMatchedException
     *@deprecated Use DatabaseExecutor::using($driver)->executeAndGetId() instead. This method will be removed in version 7.0.
     */
    #[Override]
    public function executeAndGetId(string|SqlStatement $sql, ?array $array = null): mixed
    {
        $sqlString = $sql instanceof SqlStatement ? $sql->getSql() : $sql;
        $driver = $this->matchRoute($sqlString);
        return $driver->executeAndGetId($sql, $array);
    }

    /**
     * @return string
     * @throws RouteNotInitializedException If no driver has been matched yet and no drivers are configured
     */
    #[Override]
    public function getDbHelperClass(): string
    {
        if ($this->lastMatchedDriver === null) {
            // Try to use a fallback driver (first available driver from any route)
            foreach ($this->drivers as $drivers) {
                if (!empty($drivers)) {
                    return $drivers[0]->getDbHelperClass();
                }
            }
            throw new RouteNotInitializedException('Cannot get helper class: no database has been selected and no drivers are configured.');
        }
        return $this->lastMatchedDriver->getDbHelperClass();
    }

    /**
     * @return DbFunctionsInterface
     * @throws RouteNotInitializedException If no driver has been matched yet and no drivers are configured
     */
    #[Override]
    public function getDbHelper(): DbFunctionsInterface
    {
        if ($this->lastMatchedDriver === null) {
            // Try to use a fallback driver (first available driver from any route)
            foreach ($this->drivers as $drivers) {
                if (!empty($drivers)) {
                    return $drivers[0]->getDbHelper();
                }
            }
            throw new RouteNotInitializedException('Cannot get helper: no database has been selected and no drivers are configured.');
        }
        return $this->lastMatchedDriver->getDbHelper();
    }

    /**
     * @return Uri
     * @throws RouteNotInitializedException If no driver has been matched yet
     */
    #[Override]
    public function getUri(): Uri
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot get URI: no database has been selected. Execute a query first to match a route.');
        }
        return $this->lastMatchedDriver->getUri();
    }

    /**
     * @return bool
     * @throws RouteNotInitializedException If no driver has been matched yet
     */
    #[Override]
    public function isSupportMultiRowset(): bool
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot check multi-rowset support: no database has been selected. Execute a query first to match a route.');
        }
        return $this->lastMatchedDriver->isSupportMultiRowset();
    }

    /**
     * @param bool $multipleRowSet
     * @return void
     * @throws RouteNotInitializedException If no driver has been matched yet
     */
    #[Override]
    public function setSupportMultiRowset(bool $multipleRowSet): void
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot set multi-rowset support: no database has been selected. Execute a query first to match a route.');
        }
        $this->lastMatchedDriver->setSupportMultiRowset($multipleRowSet);
    }

    public function getMaxStmtCache(): int
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot get max statement cache: no database has been selected. Execute a query first to match a route.');
        }
        return $this->lastMatchedDriver->getMaxStmtCache();
    }

    public function setMaxStmtCache(int $maxStmtCache): void
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot set max statement cache: no database has been selected. Execute a query first to match a route.');
        }
        $this->lastMatchedDriver->setMaxStmtCache($maxStmtCache);
    }

    public function getCountStmtCache(): int
    {
        if ($this->lastMatchedDriver === null) {
            return 0;
        }
        return $this->lastMatchedDriver->getCountStmtCache();
    }
    //</editor-fold>
    #[Override]
    public function reconnect(bool $force = false): bool
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot reconnect: no database has been selected. Execute a query first to match a route.');
        }
        return $this->lastMatchedDriver->reconnect($force);
    }

    #[Override]
    public function disconnect(): void
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot disconnect: no database has been selected. Execute a query first to match a route.');
        }
        $this->lastMatchedDriver->disconnect();
    }

    #[Override]
    public function isConnected(bool $softCheck = false, bool $throwError = false): bool
    {
        if ($this->lastMatchedDriver === null) {
            return false;
        }
        return $this->lastMatchedDriver->isConnected($softCheck, $throwError);
    }

    #[Override]
    public function enableLogger(LoggerInterface $logger): void
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot enable logger: no database has been selected. Execute a query first to match a route.');
        }
        $this->lastMatchedDriver->enableLogger($logger);
    }

    #[Override]
    public function log(string $message, array $context = []): void
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot log: no database has been selected. Execute a query first to match a route.');
        }
        $this->lastMatchedDriver->log($message, $context);
    }

    #[Override]
    public function hasActiveTransaction(): bool
    {
        if ($this->lastMatchedDriver === null) {
            return false;
        }
        return $this->lastMatchedDriver->hasActiveTransaction();
    }

    #[Override]
    public function requiresTransaction(): void
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot require transaction: no database has been selected. Execute a query first to match a route.');
        }
        $this->lastMatchedDriver->requiresTransaction();
    }

    #[Override]
    public function activeIsolationLevel(): ?IsolationLevelEnum
    {
        if ($this->lastMatchedDriver === null) {
            return null;
        }
        return $this->lastMatchedDriver->activeIsolationLevel();
    }

    #[Override]
    public function remainingCommits(): int
    {
        if ($this->lastMatchedDriver === null) {
            return 0;
        }
        return $this->lastMatchedDriver->remainingCommits();
    }

    /**
     * Creates a database driver-specific iterator for query results
     *
     * @param mixed $statement The statement to create an iterator from (PDOStatement, resource, etc.)
     * @param int $preFetch Number of rows to prefetch
     * @param string|null $entityClass Optional entity class name to return rows as objects
     * @param PropertyHandlerInterface|null $entityTransformer Optional transformation function for customizing entity mapping
     * @return GenericDbIterator|GenericIterator The driver-specific iterator for the query results
     * @throws RouteNotInitializedException If no driver has been matched yet
     */
    #[Override]
    public function getDriverIterator(mixed $statement, int $preFetch = 0, ?string $entityClass = null, ?PropertyHandlerInterface $entityTransformer = null): GenericDbIterator|GenericIterator
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot get driver iterator: no database has been selected. Execute a query first to match a route.');
        }
        return $this->lastMatchedDriver->getDriverIterator($statement, $preFetch, $entityClass, $entityTransformer);
    }

    #[Override]
    public function processMultiRowset(mixed $statement): void
    {
        if ($this->lastMatchedDriver === null) {
            throw new RouteNotInitializedException('Cannot process multi-rowset: no database has been selected. Execute a query first to match a route.');
        }
        $this->lastMatchedDriver->processMultiRowset($statement);
    }
}
