<?php

namespace TestsDb\AnyDataset;

use ByJG\AnyDataset\Db\Factory;

require_once 'PdoDblibTest.php';

class PdoSqlsrvTest extends PdoDblibTest
{

    protected function createInstance()
    {
        $host = getenv('MSSQL_TEST_HOST');
        if (empty($host)) {
            $host = "127.0.0.1";
        }
        $password = getenv('MSSQL_PASSWORD');
        if (empty($password)) {
            $password = 'Pa55word';
        }

        $this->dbDriver = Factory::getDbRelationalInstance("sqlsrv://sa:$password@$host/tempdb");
    }
}
