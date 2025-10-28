<?php

namespace TestDb;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use PDOException;

class PdoMySqlTest extends BasePdo
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

        $dbDriver = Factory::getDbInstance("mysql://root:$password@$host");
        DatabaseExecutor::using($dbDriver)->execute('CREATE DATABASE IF NOT EXISTS test');
        return Factory::getDbInstance("mysql://root:$password@$host/test");
    }

    protected function createDatabase()
    {
        //create the database
        $this->executor->execute("CREATE TABLE Dogs (Id INTEGER PRIMARY KEY auto_increment, Breed VARCHAR(50), Name VARCHAR(50), Age INTEGER, Weight NUMERIC(10,2))");
    }

    public function deleteDatabase()
    {
        $this->executor->execute('drop table Dogs;');
    }

    public function testGetDate() {
        $data = $this->executor->getScalar("SELECT CONVERT('2018-07-26 20:02:03', date) ");
        $this->assertEquals("2018-07-26", $data);

        $data = $this->executor->getScalar("SELECT CONVERT('2018-07-26 20:02:03', datetime) ");
        $this->assertEquals("2018-07-26 20:02:03", $data);
    }

    public function testDontParseParam_3() {
        $this->expectException(PDOException::class);
        
        parent::testDontParseParam_3();
    }

    public function testCheckInitialParameters()
    {
        $this->assertStringStartsWith('utf8', $this->executor->getScalar("SELECT @@character_set_client"));
        $this->assertStringStartsWith('utf8', $this->executor->getScalar("SELECT @@character_set_results"));
        $this->assertStringStartsWith('utf8', $this->executor->getScalar("SELECT @@character_set_connection"));
    }
}
