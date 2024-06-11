<?php

namespace TestsDb\AnyDataset;

use ByJG\AnyDataset\Db\Factory;

require_once 'PdoDblibTest.php';

class PdoSqlsrvTest extends PdoDblibTest
{

    protected function createInstance()
    {
        $this->floatSize = 53;
        $host = getenv('MSSQL_TEST_HOST');
        if (empty($host)) {
            $host = "127.0.0.1";
        }
        $password = getenv('MSSQL_PASSWORD');
        if (empty($password)) {
            $password = 'Pa55word';
        }

        return Factory::getDbRelationalInstance("sqlsrv://sa:$password@$host/tempdb");
    }

    public function testGetDate() {
        $data = $this->dbDriver->getScalar("SELECT CONVERT(date, '2018-07-26 20:02:03') ");
        $this->assertEquals("2018-07-26", $data);

        $data = $this->dbDriver->getScalar("SELECT CONVERT(datetime, '2018-07-26 20:02:03') ");
        $this->assertEquals("2018-07-26 20:02:03.000", $data);
    }
}
