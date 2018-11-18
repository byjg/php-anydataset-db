<?php

namespace ByJG\AnyDataset\Db;

use ByJG\Util\Uri;

class Factory
{
    /**
     * @param $connectionString
     * @param $schemesAlternative
     * @return \ByJG\AnyDataset\Db\DbDriverInterface
     */
    public static function getDbRelationalInstance($connectionString, $schemesAlternative = null)
    {
        $prefix = '\\ByJG\\AnyDataset\\Db\\';

        return self::getInstance(
            $connectionString,
            array_merge(
                [
                    "oci8" => $prefix . "DbOci8Driver",
                    "dblib" => $prefix . "PdoDblib",
                    "mysql" => $prefix . "PdoMysql",
                    "pgsql" => $prefix . "PdoPgsql",
                    "oci" => $prefix . "PdoOci",
                    "odbc" => $prefix . "PdoOdbc",
                    "sqlite" => $prefix . "PdoSqlite",
                ],
                (array)$schemesAlternative
            ),
            DbDriverInterface::class
        );
    }


    protected static function getInstance($connectionString, $validSchemes, $typeOf)
    {
        $connectionUri = new Uri($connectionString);

        $scheme = $connectionUri->getScheme();

        $class = isset($validSchemes[$scheme]) ? $validSchemes[$scheme] : PdoLiteral::class;

        $instance = new $class($connectionUri);

        if (!is_a($instance, $typeOf)) {
            throw new \InvalidArgumentException(
                "The class '$typeOf' is not a instance of DbDriverInterface"
            );
        }

        return $instance;
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
