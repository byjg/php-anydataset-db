<?php

namespace TestsDb\AnyDataset;

use ByJG\AnyDataset\Db\Factory;

require_once 'BasePdo.php';

class PdoOciTest extends BasePdo
{

    protected function createInstance()
    {
        $this->escapeQuote = "\'";

        $host = getenv('MYSQL_TEST_HOST');
        if (empty($host)) {
            $host = "127.0.0.1";
        }
        $password = getenv('MYSQL_PASSWORD');
        if (empty($password)) {
            $password = 'password';
        }
        if ($password == '.') {
            $password = "";
        }

        return Factory::getDbRelationalInstance("oci8://sys:$password@127.0.0.1/FREEPDB1");
    }

    protected function createDatabase()
    {
        //create the database
        $this->dbDriver->execute("CREATE TABLE Dogs (Id NUMBER GENERATED ALWAYS as IDENTITY(START with 1 INCREMENT by 1), Breed varchar2(50), Name varchar2(50), Age number(10), Weight number(10,2), CONSTRAINT dogs_pk PRIMARY KEY (Id))");
    }

    public function deleteDatabase()
    {
        $this->dbDriver->execute('drop table Dogs');
    }

    public function testGetDate() {
        $data = $this->dbDriver->getScalar("SELECT TO_DATE('2018-07-26', 'YYYY-MM-DD') FROM DUAL ");
        $this->assertEquals("26-JUL-18", $data);

        $data = $this->dbDriver->getScalar("SELECT TO_TIMESTAMP('2018-07-26 20:02:03', 'YYYY-MM-DD HH24:MI:SS') FROM DUAL ");
        $this->assertEquals("26-JUL-18 08.02.03.000000000 PM", $data);
    }

//    public function testDontParseParam_3() {
//        $this->expectException(\PDOException::class);
//
//        parent::testDontParseParam_3();
//    }

}
