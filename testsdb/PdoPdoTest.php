<?php

namespace TestDb;

use ByJG\AnyDataset\Db\Factory;
use ByJG\Util\Uri;

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

        $this->dbDriver = Factory::getDbRelationalInstance(
            Uri::getInstanceFromString("pdo://postgres:$password@pgsql")
                ->withQueryKeyValue("host", $host));

        $exists = $this->dbDriver->getScalar('select count(1) from pg_catalog.pg_database where datname = \'testpdo\'');
        if ($exists == 0) {
            $this->dbDriver->execute('CREATE DATABASE testpdo');
        }

        $this->dbDriver = Factory::getDbRelationalInstance(
            Uri::getInstanceFromString("pdo://postgres:$password@pgsql")
                ->withQueryKeyValue("host", $host)
                ->withQueryKeyValue("dbname", "testpdo"));
    }

    public function testDontParseParam_3() {
        $this->expectException(\PDOException::class);
        
        parent::testDontParseParam_3();
    }
}
