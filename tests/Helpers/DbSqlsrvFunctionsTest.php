<?php

namespace Test\Helpers;

use ByJG\AnyDataset\Db\Helpers\DbSqlsrvFunctions;

class DbSqlsrvFunctionsTest extends DbDblibFunctionsTest
{
    protected function setUp(): void
    {
        $this->object = new DbSqlsrvFunctions();
    }
}
