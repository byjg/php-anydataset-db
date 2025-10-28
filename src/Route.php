<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotImplementedException;
use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Exception\RouteNotFoundException;
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
     * @var array<string, DbDriverInterface[]>|array<string, string[]>
     */
    protected array $dbDriverInterface = [];

    /**
     * @var string[]
     */
    protected array $routes;

    /**
     * Route constructor.
     */
    public function __construct()
    {
    }

    //<editor-fold desc="Route Methods">

    /**
     * @param string $routeName
     * @param string|DbDriverInterface|DbDriverInterface[]|string[] $dbDriver
     * @return $this
     */
    public function addDbDriverInterface(string $routeName, array|string|DbDriverInterface $dbDriver): static
    {
        if (!isset($this->dbDriverInterface[$routeName])) {
            $this->dbDriverInterface[$routeName] = [];
        }

        if (!is_array($dbDriver)) {
            $dbDriver = [$dbDriver];
        }

        foreach ($dbDriver as $item) {
            if (!is_string($item) && !($item instanceof DbDriverInterface)) {
                throw new InvalidArgumentException('Invalid dbDriver');
            }
            $this->dbDriverInterface[$routeName][] = $item;
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
            $table = '\w+';
        }
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
        if (!isset($this->dbDriverInterface[$routeName])) {
            throw new RouteNotFoundException("Invalid route $routeName");
        }
        $this->routes[$regEx] = $routeName;
        return $this;
    }

    /**
     * @param string $sql
     * @return DbDriverInterface
     * @throws RouteNotMatchedException
     */
    public function matchRoute(string $sql): DbDriverInterface
    {
        $sql = trim(strtolower(str_replace("\n", " ", $sql))) . ' ';
        foreach ($this->routes as $pattern => $routeName) {
            if (!preg_match("/$pattern/", $sql)) {
                continue;
            }

            $item = rand(0, count($this->dbDriverInterface[$routeName])-1);
            $dbDriver = $this->dbDriverInterface[$routeName][$item];
            if (is_string($dbDriver)) {
                $dbDriver = Factory::getDbInstance($dbDriver);
                $this->dbDriverInterface[$routeName][$item] = $dbDriver;
            }

            return $dbDriver;
        }

        throw new RouteNotMatchedException('Route not matched');
    }
    //</editor-fold>

    //<editor-fold desc="DbDriverInterface">

    #[Override]
    public function prepareStatement(string $sql, ?array $params = null, ?array &$cacheInfo = []): mixed
    {
        // TODO: Implement prepareStatement() method.
        return null;
    }

    #[Override]
    public function executeCursor(mixed $statement): void
    {
        // TODO: Implement executeCursor() method.
    }

    /**
     * @param string|SqlStatement $sql
     * @param array|null $params
     * @param int $preFetch
     * @return GenericDbIterator|GenericIterator
     * @throws RouteNotMatchedException
     */
    #[Override]
    public function getIterator(string|SqlStatement $sql, ?array $params = null, int $preFetch = 0): GenericDbIterator|GenericIterator
    {
        $sqlString = $sql instanceof SqlStatement ? $sql->getSql() : $sql;
        $dbDriver = $this->matchRoute($sqlString);
        return $dbDriver->getIterator($sql, $params, $preFetch);
    }

    /**
     * @param string|SqlStatement $sql
     * @param array|null $array
     * @return mixed
     * @throws RouteNotMatchedException
     */
    #[Override]
    public function getScalar(string|SqlStatement $sql, ?array $array = null): mixed
    {
        $dbDriver = $this->matchRoute($sql);
        return $dbDriver->getScalar($sql, $array);
    }

    /**
     * @param string $tablename
     * @throws NotImplementedException
     */
    #[Override]
    public function getAllFields(string $tablename): array
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @param string|SqlStatement $sql
     * @param array|null $array
     * @return bool
     * @throws RouteNotMatchedException
     */
    #[Override]
    public function execute(string|SqlStatement $sql, ?array $array = null): bool
    {
        $dbDriver = $this->matchRoute($sql);
        return $dbDriver->execute($sql, $array);
    }

    /**
     * @param IsolationLevelEnum|null $isolationLevel
     * @throws NotImplementedException
     */
    #[Override]
    public function beginTransaction(IsolationLevelEnum $isolationLevel = null, bool $allowJoin = false)
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @throws NotImplementedException
     */
    #[Override]
    public function commitTransaction(): void
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @throws NotImplementedException
     */
    #[Override]
    public function rollbackTransaction(): void
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @return mixed
     * @throws NotImplementedException
     */
    #[Override]
    public function getDbConnection(): mixed
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @param string $name
     * @param mixed $value
     * @throws NotImplementedException
     */
    public function setAttribute(string $name, mixed $value): void
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @param string $name
     * @throws NotImplementedException
     */
    public function getAttribute(string $name): mixed
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @param string|SqlStatement $sql
     * @param array|null $array
     * @return mixed
     * @throws RouteNotMatchedException
     */
    #[Override]
    public function executeAndGetId(string|SqlStatement $sql, ?array $array = null): mixed
    {
        $sqlString = $sql instanceof SqlStatement ? $sql->getSql() : $sql;
        $dbDriver = $this->matchRoute($sqlString);
        return $dbDriver->executeAndGetId($sql, $array);
    }

    /**
     * @return DbFunctionsInterface
     * @throws NotImplementedException
     */
    #[Override]
    public function getDbHelper(): DbFunctionsInterface
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @return Uri
     * @throws NotImplementedException
     */
    #[Override]
    public function getUri(): Uri
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @throws NotImplementedException
     */
    #[Override]
    public function isSupportMultiRowset(): bool
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @param bool $multipleRowSet
     * @throws NotImplementedException
     */
    #[Override]
    public function setSupportMultiRowset(bool $multipleRowSet): void
    {
        throw new NotImplementedException('Feature not available');
    }

    public function getMaxStmtCache(): int
    {
        throw new NotImplementedException('Feature not available');
    }

    public function setMaxStmtCache(int $maxStmtCache): void
    {
        throw new NotImplementedException('Feature not available');
    }

    public function getCountStmtCache(): int
    {
        throw new NotImplementedException('Feature not available');
    }
    //</editor-fold>
    #[Override]
    public function reconnect(bool $force = false): bool
    {
        throw new NotImplementedException('Feature not available');
    }

    #[Override]
    public function disconnect(): void
    {
        throw new NotImplementedException('Feature not available');
    }

    #[Override]
    public function isConnected(bool $softCheck = false, bool $throwError = false): bool
    {
        throw new NotImplementedException('Feature not available');
    }

    #[Override]
    public function enableLogger(LoggerInterface $logger): void
    {
        throw new NotImplementedException('Feature not available');
    }

    #[Override]
    public function log(string $message, array $context = []): void
    {
        throw new NotImplementedException('Feature not available');
    }

    #[Override]
    public function hasActiveTransaction(): bool
    {
        throw new NotImplementedException('Feature not available');
    }

    #[Override]
    public function requiresTransaction(): void
    {
        throw new NotImplementedException('Feature not available');
    }

    #[Override]
    public function activeIsolationLevel(): ?IsolationLevelEnum
    {
        throw new NotImplementedException('Feature not available');
    }

    #[Override]
    public function remainingCommits(): int
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * Creates a database driver-specific iterator for query results
     *
     * @param mixed $statement The statement to create an iterator from (PDOStatement, resource, etc.)
     * @param int $preFetch Number of rows to prefetch
     * @param string|null $entityClass Optional entity class name to return rows as objects
     * @param PropertyHandlerInterface|null $entityTransformer Optional transformation function for customizing entity mapping
     * @return GenericDbIterator|GenericIterator The driver-specific iterator for the query results
     * @throws NotImplementedException
     */
    #[Override]
    public function getDriverIterator(mixed $statement, int $preFetch = 0, ?string $entityClass = null, ?PropertyHandlerInterface $entityTransformer = null): GenericDbIterator|GenericIterator
    {
        // For Route, we don't know which driver to use without SQL context
        // This method should generally not be called directly on Route
        throw new NotImplementedException('getDriverIterator cannot be called directly on Route');
    }

    #[Override]
    public function processMultiRowset(mixed $statement): void
    {
        // TODO: Implement processMultiRowset() method.
    }
}
