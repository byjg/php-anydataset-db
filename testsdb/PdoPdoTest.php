<?php

namespace TestDb;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\Util\Uri;
use PDOException;

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

        $dbDriver = Factory::getDbInstance(
            Uri::getInstance("pdo://postgres:$password@pgsql")
                ->withQueryKeyValue("host", $host));
        $executor = DatabaseExecutor::using($dbDriver);

        $exists = $executor->getScalar('select count(1) from pg_catalog.pg_database where datname = \'testpdo\'');
        if ($exists == 0) {
            $executor->execute('CREATE DATABASE testpdo');
        }

        return Factory::getDbInstance(
            Uri::getInstance("pdo://postgres:$password@pgsql")
                ->withQueryKeyValue("host", $host)
                ->withQueryKeyValue("dbname", "testpdo"));
    }

    public function testDontParseParam_3() {
        $this->expectException(PDOException::class);
        
        parent::testDontParseParam_3();
    }
}
