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
    public static function registerDbDriver($class)
    {
        if (!in_array(DbDriverInterface::class, class_implements($class))) {
            throw new InvalidArgumentException(
                "The class '$class' is not a instance of DbDriverInterface"
            );
        }

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
     * @param $connectionString
     * @return DbDriverInterface
     */
    public static function getDbRelationalInstance($connectionString)
    {
        return self::getDbInstance(new Uri($connectionString));
    }


    /**
     * @param $connectionUri Uri
     * @return mixed
     */
    public static function getDbInstance($connectionUri)
    {

        if (empty(self::$config)) {
            self::registerDbDriver(PdoMysql::class);
            self::registerDbDriver(PdoPgsql::class);
            self::registerDbDriver(PdoSqlite::class);
            self::registerDbDriver(PdoDblib::class);
            self::registerDbDriver(PdoSqlsrv::class);
            self::registerDbDriver(PdoOdbc::class);
            self::registerDbDriver(PdoPdo::class);
            self::registerDbDriver(PdoOci::class);
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
     * @param \ByJG\Util\Uri $connectionUri
     * @return \ByJG\AnyDataset\Db\DbFunctionsInterface
     */
    public static function getDbFunctions(Uri $connectionUri)
    {
        $dbFunc = "\\ByJG\\AnyDataset\\Db\\Helpers\\Db"
            . ucfirst($connectionUri->getScheme())
            . "Functions";
        return new $dbFunc();
    }
}
