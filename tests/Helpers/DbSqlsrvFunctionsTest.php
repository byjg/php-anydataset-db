<?php

namespace Test\Helpers;

use ByJG\AnyDataset\Db\SqlDialect\SqlsrvSqlDialect;
use Override;

/**
 * Class DbSqlsrvFunctionsTest
 *
 * Tests for the SQLSRV database functions
 */
class DbSqlsrvFunctionsTest extends DbDblibFunctionsTest
{
    /**
     * Set up the test object
     */
    #[Override]
    protected function setUp(): void
    {
        $this->object = new SqlsrvSqlDialect();
    }
}
