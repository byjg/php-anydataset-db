<?php

namespace ByJG\AnyDataset\Db;

use ByJG\Util\Uri;
use InvalidArgumentException;

class Factory
{
    private static $config = [];

    /**
     * @param string $class
     * @return void
     */
    public static function registerDbDriver(string $class): void
    {
        if (!in_array(DbDriverInterface::class, class_implements($class))) {
            throw new InvalidArgumentException(
                "The class '$class' is not a instance of DbDriverInterface"
            );
        }

        /** @var DbDriverInterface $class */
        if (empty($class::schema())) {
            throw new InvalidArgumentException(
                "The class '$class' must implement the static method schema()"
            );
        }

        $protocolList = $class::schema();
        foreach ((array)$protocolList as $item) {
            self::$config[$item] = $class;
        }
    }

    /**
     * @param string $connectionString
     * @return DbDriverInterface
     * @deprecated Use getDbInstance instead
     */
    public static function getDbRelationalInstance(string $connectionString): DbDriverInterface
    {
        return self::getDbInstance(new Uri($connectionString));
    }


    /**
     * @param Uri|string $connectionUri Uri
     * @return DbDriverInterface
     */
    public static function getDbInstance(Uri|string $connectionUri): DbDriverInterface
    {
        if (is_string($connectionUri)) {
            $connectionUri = new Uri($connectionUri);
        }

        if (empty(self::$config)) {
            self::registerDbDriver(PdoMysql::class);
            self::registerDbDriver(PdoPgsql::class);
            self::registerDbDriver(PdoSqlite::class);
            self::registerDbDriver(PdoDblib::class);
            self::registerDbDriver(PdoSqlsrv::class);
            self::registerDbDriver(PdoOdbc::class);
            self::registerDbDriver(PdoPdo::class);
            self::registerDbDriver(PdoOci::class);
            self::registerDbDriver(DbOci8Driver::class);
        }

        $scheme = $connectionUri->getScheme();

        if (!isset(self::$config[$scheme])) {
            throw new InvalidArgumentException("The '$scheme' scheme does not exist.");
        }

        $class = self::$config[$scheme];

        return new $class($connectionUri);
    }

    /**
     * Get a IDbFunctions class to execute Database specific operations.
     *
     * @param Uri $connectionUri
     * @return DbFunctionsInterface
     */
    public static function getDbFunctions(Uri $connectionUri): DbFunctionsInterface
    {
        $dbFunc = "\\ByJG\\AnyDataset\\Db\\Helpers\\Db"
            . ucfirst($connectionUri->getScheme())
            . "Functions";
        return new $dbFunc();
    }
}
