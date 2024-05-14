<?php

namespace ByJG\AnyDataset\Db\Interfaces;

interface DbCacheInterface
{
    public function getMaxStmtCache();

    public function getCountStmtCache();

    public function setMaxStmtCache($maxStmtCache);

}