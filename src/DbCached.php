<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Lists\ArrayDataset;
use ByJG\Util\Uri;
use DateInterval;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

class DbCached implements DbDriverInterface
{
    /**
     * @var DbDriverInterface|null
     */
    protected $dbDriver = null;

    /**
     * @var CacheItemPoolInterface;
     */
    protected $cacheEngine = null;

    protected $timeToCache = 30;

    /**
     * DbCached constructor.
     *
     * @param DbDriverInterface|null $dbDriver
     * @param CacheItemPoolInterface $cacheEngine
     * @param int $timeToCache
     */
    public function __construct(DbDriverInterface $dbDriver, CacheItemPoolInterface $cacheEngine, $timeToCache = 30)
    {
        $this->dbDriver = $dbDriver;
        $this->cacheEngine = $cacheEngine;
        $this->timeToCache = $timeToCache;
    }

    /**
     * @param string $sql
     * @param null $params
     * @return GenericIterator
     * @throws InvalidArgumentException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function getIterator($sql, $params = null)
    {
        // Otherwise try to get from cache
        $key = $this->getQueryKey($sql, $params);

        // Get the CACHE
        $cacheItem = $this->cacheEngine->getItem($key);
        if (!$cacheItem->isHit()) {
            $iterator = $this->dbDriver->getIterator($sql, $params);

            $cacheItem->set($iterator->toArray());
            $cacheItem->expiresAfter(DateInterval::createFromDateString($this->timeToCache . " seconds"));

            $this->cacheEngine->save($cacheItem);
        }

        $arrayDS = new ArrayDataset($cacheItem->get());
        return $arrayDS->getIterator();
    }

    protected function array_map_assoc( $callback , $array ){
        $r = array();
        foreach ($array as $key=>$value) {
            $r[$key] = $callback($key, $value);
        }
        return $r;
    }

    protected function getQueryKey($sql, $array)
    {
        $key1 = md5($sql);
        $key2 = "";

        // Check which parameter exists in the SQL
        if (is_array($array)) {
            $key2 = md5(":" . implode(',', $this->array_map_assoc(function($k,$v){return "$k:$v";},$array)));
        }

        return  "qry:" . $key1 . $key2;
    }

    public function getScalar($sql, $array = null)
    {
        $this->dbDriver->getScalar($sql, $array);
    }

    public function getAllFields($tablename)
    {
        $this->dbDriver->getAllFields($tablename);
    }

    public function execute($sql, $array = null)
    {
        $this->dbDriver->execute($sql, $array);
    }

    public function beginTransaction()
    {
        $this->dbDriver->beginTransaction();
    }

    public function commitTransaction()
    {
        $this->dbDriver->commitTransaction();
    }

    public function rollbackTransaction()
    {
        $this->dbDriver->rollbackTransaction();
    }

    public function getDbConnection()
    {
        return $this->dbDriver->getDbConnection();
    }

    public function setAttribute($name, $value)
    {
        $this->dbDriver->setAttribute($name, $value);
    }

    public function getAttribute($name)
    {
        $this->dbDriver->getAttribute($name);
    }

    public function executeAndGetId($sql, $array = null)
    {
        $this->dbDriver->executeAndGetId($sql, $array);
    }

    public function getDbHelper()
    {
        return $this->dbDriver->getDbHelper();
    }

    /**
     * @return Uri
     */
    public function getUri()
    {
        return $this->dbDriver->getUri();
    }

    public function isSupportMultRowset()
    {
        return $this->dbDriver->isSupportMultRowset();
    }

    public function setSupportMultRowset($multipleRowSet)
    {
        $this->dbDriver->setSupportMultRowset($multipleRowSet);
    }
}
