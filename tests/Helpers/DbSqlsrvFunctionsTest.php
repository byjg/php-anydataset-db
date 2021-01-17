<?php

namespace Tests\AnyDataset\Store\Helpers;

use ByJG\AnyDataset\Db\Helpers\DbDblibFunctions;
use ByJG\AnyDataset\Db\Helpers\DbSqlsrvFunctions;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/DbDblibFunctionsTest.php";

class DbSqlsrvFunctionsTest extends DbDblibFunctionsTest
{
    protected function setUp()
    {
        $this->object = new DbSqlsrvFunctions();
    }
}
