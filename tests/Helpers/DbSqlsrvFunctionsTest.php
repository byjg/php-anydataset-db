<?php

namespace Test\Helpers;

use ByJG\AnyDataset\Db\Helpers\DbSqlsrvFunctions;
use Override;

class DbSqlsrvFunctionsTest extends DbDblibFunctionsTest
{
    #[Override]
    protected function setUp(): void
    {
        $this->object = new DbSqlsrvFunctions();
    }
}
