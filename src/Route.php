<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotImplementedException;
use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Exception\RouteNotFoundException;
use ByJG\AnyDataset\Db\Exception\RouteNotMatchedException;
use PDO;
use Psr\Log\LoggerInterface;

class Route implements DbDriverInterface
{
    public static function schema()
    {
        return null;
    }

    /**
     * @var array(DbDriverInterface[])
     */
    protected $dbDriverInterface = [];

    /**
     * @var string[]
     */
    protected $routes;

    /**
     * Route constructor.
     */
    public function __construct()
    {
    }

    //<editor-fold desc="Route Methods">

    /**
     * @param string $routeName
     * @param DbDriverInterface[]|DbDriverInterface|string|string[] $dbDriver
     * @return \ByJG\AnyDataset\Db\Route
     */
    public function addDbDriverInterface($routeName, $dbDriver)
    {
        if (!isset($this->dbDriverInterface[$routeName])) {
            $this->dbDriverInterface[$routeName] = [];
        }

        if (!is_array($dbDriver)) {
            $dbDriver = [$dbDriver];
        }

        foreach ($dbDriver as $item) {
            $this->dbDriverInterface[$routeName][] = $item;
        }

        return $this;
    }

    /**
     * @param $routeName
     * @param null $table
     * @return \ByJG\AnyDataset\Db\Route
     * @throws \ByJG\AnyDataset\Db\Exception\RouteNotFoundException
     */
    public function addRouteForSelect($routeName, $table = null)
    {
        if (empty($table)) {
            $table = '\w+';
        }
        return $this->addCustomRoute($routeName, '^select.*from\s+([`]?' . $table . '[`]?)\s');
    }

    /**
     * @param $routeName
     * @param null $table
     * @return \ByJG\AnyDataset\Db\Route
     * @throws \ByJG\AnyDataset\Db\Exception\RouteNotFoundException
     */
    public function addRouteForInsert($routeName, $table = null)
    {
        if (empty($table)) {
            $table = '\w+';
        }
        return $this->addCustomRoute($routeName, '^insert\s+into\s+([`]?' . $table . '[`]?)\s+\(');
    }

    /**
     * @param $routeName
     * @param null $table
     * @return \ByJG\AnyDataset\Db\Route
     * @throws \ByJG\AnyDataset\Db\Exception\RouteNotFoundException
     */
    public function addRouteForUpdate($routeName, $table = null)
    {
        if (empty($table)) {
            $table = '\w+';
        }
        return $this->addCustomRoute($routeName, '^update\s+([`]?' . $table . '[`]?)\s+set');
    }

    /**
     * @param $routeName
     * @param null $table
     * @return \ByJG\AnyDataset\Db\Route
     * @throws \ByJG\AnyDataset\Db\Exception\RouteNotFoundException
     */
    public function addRouteForDelete($routeName, $table = null)
    {
        if (empty($table)) {
            $table = '\w+';
        }
        return $this->addCustomRoute($routeName, '^delete\s+(from\s+)?([`]?' . $table . '[`]?)\s');
    }

    /**
     * @param $routeName
     * @param $table
     * @return \ByJG\AnyDataset\Db\Route
     * @throws \ByJG\AnyDataset\Db\Exception\RouteNotFoundException
     */
    public function addRouteForTable($routeName, $table)
    {
        $this->addRouteForRead($routeName, $table);
        $this->addRouteForWrite($routeName, $table);
        return $this;
    }

    /**
     * @param $routeName
     * @param null $table
     * @return \ByJG\AnyDataset\Db\Route
     * @throws \ByJG\AnyDataset\Db\Exception\RouteNotFoundException
     */
    public function addRouteForWrite($routeName, $table = null)
    {
        $this->addRouteForInsert($routeName, $table);
        $this->addRouteForUpdate($routeName, $table);
        $this->addRouteForDelete($routeName, $table);
        return $this;
    }

    /**
     * @param $routeName
     * @param null $table
     * @return \ByJG\AnyDataset\Db\Route
     * @throws \ByJG\AnyDataset\Db\Exception\RouteNotFoundException
     */
    public function addRouteForRead($routeName, $table = null)
    {
        return $this->addRouteForSelect($routeName, $table);
    }

    /**
     * @param $routeName
     * @param $field
     * @param $value
     * @return \ByJG\AnyDataset\Db\Route
     * @throws \ByJG\AnyDataset\Db\Exception\RouteNotFoundException
     */
    public function addRouteForFilter($routeName, $field, $value)
    {
        return $this->addCustomRoute($routeName, "\\s`?$field`?\\s*=\\s*'?$value'?\s");
    }

    /**
     * @param $routeName
     * @return \ByJG\AnyDataset\Db\Route
     * @throws \ByJG\AnyDataset\Db\Exception\RouteNotFoundException
     */
    public function addDefaultRoute($routeName)
    {
        return $this->addCustomRoute($routeName, '.');
    }

    /**
     * @param $routeName
     * @param $regEx
     * @return \ByJG\AnyDataset\Db\Route
     * @throws \ByJG\AnyDataset\Db\Exception\RouteNotFoundException
     */
    public function addCustomRoute($routeName, $regEx)
    {
        if (!isset($this->dbDriverInterface[$routeName])) {
            throw new RouteNotFoundException("Invalid route $routeName");
        }
        $this->routes[$regEx] = $routeName;
        return $this;
    }

    /**
     * @param $sql
     * @return DbDriverInterface
     * @throws \ByJG\AnyDataset\Db\Exception\RouteNotMatchedException
     */
    public function matchRoute($sql)
    {
        $sql = trim(strtolower(str_replace("\n", " ", $sql))) . ' ';
        foreach ($this->routes as $pattern => $routeName) {
            if (!preg_match("/$pattern/", $sql)) {
                continue;
            }

            $dbDriver = $this->dbDriverInterface[$routeName][rand(0, count($this->dbDriverInterface[$routeName])-1)];
            if (is_string($dbDriver)) {
                return Factory::getDbRelationalInstance($dbDriver);
            }

            return $dbDriver;
        }

        throw new RouteNotMatchedException('Route not matched');
    }
    //</editor-fold>

    //<editor-fold desc="DbDriverInterface">

    /**
     * @param string $sql
     * @param null $params
     * @return GenericIterator
     * @throws \ByJG\AnyDataset\Db\Exception\RouteNotMatchedException
     */
    public function getIterator($sql, $params = null)
    {
        $dbDriver = $this->matchRoute($sql);
        return $dbDriver->getIterator($sql, $params);
    }

    /**
     * @param $sql
     * @param null $array
     * @return mixed
     * @throws \ByJG\AnyDataset\Db\Exception\RouteNotMatchedException
     */
    public function getScalar($sql, $array = null)
    {
        $dbDriver = $this->matchRoute($sql);
        return $dbDriver->getScalar($sql, $array);
    }

    /**
     * @param $tablename
     * @throws NotImplementedException
     */
    public function getAllFields($tablename)
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @param $sql
     * @param null $array
     * @return mixed
     * @throws \ByJG\AnyDataset\Db\Exception\RouteNotMatchedException
     */
    public function execute($sql, $array = null)
    {
        $dbDriver = $this->matchRoute($sql);
        return $dbDriver->execute($sql, $array);
    }

    /**
     * @param IsolationLevelEnum|null $isolationLevel
     * @throws NotImplementedException
     */
    public function beginTransaction(IsolationLevelEnum $isolationLevel = null, bool $allowJoin = false)
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @throws NotImplementedException
     */
    public function commitTransaction(): void
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @throws NotImplementedException
     */
    public function rollbackTransaction(): void
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @return PDO|void
     * @throws NotImplementedException
     */
    public function getDbConnection()
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @param $name
     * @param $value
     * @throws NotImplementedException
     */
    public function setAttribute($name, $value)
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @param $name
     * @throws NotImplementedException
     */
    public function getAttribute($name)
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @param $sql
     * @param null $array
     * @return mixed
     * @throws \ByJG\AnyDataset\Db\Exception\RouteNotMatchedException
     */
    public function executeAndGetId($sql, $array = null)
    {
        $dbDriver = $this->matchRoute($sql);
        return $dbDriver->executeAndGetId($sql, $array);
    }

    /**
     * @return \ByJG\AnyDataset\Db\DbFunctionsInterface|void
     * @throws NotImplementedException
     */
    public function getDbHelper()
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @return void
     * @throws NotImplementedException
     */
    public function getUri()
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @throws NotImplementedException
     */
    public function isSupportMultRowset()
    {
        throw new NotImplementedException('Feature not available');
    }

    /**
     * @param $multipleRowSet
     * @throws NotImplementedException
     */
    public function setSupportMultRowset($multipleRowSet)
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
    public function reconnect($force = false)
    {
        throw new NotImplementedException('Feature not available');
    }

    public function disconnect()
    {
        throw new NotImplementedException('Feature not available');
    }

    public function isConnected($softCheck = false, $throwError = false)
    {
        throw new NotImplementedException('Feature not available');
    }

    public function enableLogger(LoggerInterface $logger)
    {
        throw new NotImplementedException('Feature not available');
    }

    public function log($message, $context = [])
    {
        throw new NotImplementedException('Feature not available');
    }

    public function hasActiveTransaction(): bool
    {
        throw new NotImplementedException('Feature not available');
    }

    public function requiresTransaction(): void
    {
        throw new NotImplementedException('Feature not available');
    }

    public function activeIsolationLevel(): ?IsolationLevelEnum
    {
        throw new NotImplementedException('Feature not available');
    }

    public function remainingCommits(): int
    {
        throw new NotImplementedException('Feature not available');
    }
}
