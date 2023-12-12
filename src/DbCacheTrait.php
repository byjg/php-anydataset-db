<?php

namespace ByJG\AnyDataset\Db;

trait DbCacheTrait
{
    protected function array_map_assoc($callback, $array)
    {
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
}