<?php

namespace ByJG\AnyDataset\Db\Traits;

use Psr\SimpleCache\CacheInterface;

trait DbCacheTrait
{
    protected function array_map_assoc($callback, $array): array
    {
        $r = array();
        foreach ($array as $key=>$value) {
            $r[$key] = $callback($key, $value);
        }
        return $r;
    }

    protected function getQueryKey(?CacheInterface $cache, $sql, $array): string|null
    {
        if (empty($cache)) {
            return null;
        }

        $key1 = md5($sql);
        $key2 = "";

        // Check which parameter exists in the SQL
        if (is_array($array)) {
            $key2 = md5(":" . implode(',', $this->array_map_assoc(function($k,$v){return "$k:$v";},$array)));
        }

        return  "qry:" . $key1 . $key2;
    }
}
