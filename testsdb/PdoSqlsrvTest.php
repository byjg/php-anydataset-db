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

    public function testGetDate() {
        $data = $this->dbDriver->getScalar("SELECT CONVERT(date, '2018-07-26 20:02:03') ");
        $this->assertEquals("2018-07-26", $data);

        $data = $this->dbDriver->getScalar("SELECT CONVERT(datetime, '2018-07-26 20:02:03') ");
        $this->assertEquals("2018-07-26 20:02:03.000", $data);
    }

    public function testDontBindParam()
    {
        try {
            parent::testDontBindParam();
            $this->fail();
        } catch (\PDOException $ex) {
            if (strpos($ex->getMessage(), "SQLSTATE[07002]") === false) {
                throw $ex;
            }
            $this->assertTrue(true);
        }
    }
}
