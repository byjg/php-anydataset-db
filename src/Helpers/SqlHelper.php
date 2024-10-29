<?php

namespace ByJG\AnyDataset\Db\Helpers;

class SqlHelper
{
    public static function createSafeSQL(string $sql, array $list): string
    {
        return str_replace(array_keys($list), array_values($list), $sql);
    }
}
