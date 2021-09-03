<?php

namespace TestsDb\AnyDataset;

use ByJG\AnyDataset\Db\Factory;

require_once 'PdoPostgresTest.php';

class PdoPdoTest extends PdoPostgresTest
{

    protected function createInstance()
    {
        $host = getenv('PSQL_TEST_HOST');
        if (empty($host)) {
            $host = "127.0.0.1";
        }
        $password = getenv('PSQL_PASSWORD');
        if (empty($password)) {
            $password = 'password';
        }
        if ($password == '.') {
            $password = "";
        }

        $pdoConn = "host=$host";
        $this->dbDriver = Factory::getDbRelationalInstance("pdo://postgres:$password@pgsql?dsn=" . urlencode($pdoConn));

        $exists = $this->dbDriver->getScalar('select count(1) from pg_catalog.pg_database where datname = \'testpdo\'');
        if ($exists == 0) {
            $this->dbDriver->execute('CREATE DATABASE testpdo');
        }

        $pdoConn = "$pdoConn;dbname=testpdo;";
        $this->dbDriver = Factory::getDbRelationalInstance("pdo://postgres:$password@pgsql?dsn=" . urlencode($pdoConn));
    }

    public function testDontBindParam()
    {
        try {
            parent::testDontBindParam();
            $this->fail();
        } catch (\PDOException $ex) {
            if (strpos($ex->getMessage(), "SQLSTATE[08P01]") === false) {
                throw $ex;
            }
            $this->assertTrue(true);
        }
    }
}
