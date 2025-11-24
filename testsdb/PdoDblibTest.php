<?php

namespace TestDb;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\DbPdoDriver;
use ByJG\AnyDataset\Db\Factory;
use PDOException;

class PdoDblibTest extends BasePdo
{

    protected function createInstance()
    {
        if (!extension_loaded('pdo_dblib')) {
            $this->testSkipped = true;
            $this->markTestSkipped("PDO DBLIB extension is not loaded");
        }

        $this->floatSize = 53;
        $host = getenv('MSSQL_TEST_HOST');
        if (empty($host)) {
            $host = "127.0.0.1";
        }
        $password = getenv('MSSQL_PASSWORD');
        if (empty($password)) {
            $password = 'Pa55word';
        }

        return Factory::getDbInstance("dblib://sa:$password@$host/tempdb");
    }

    protected function createDatabase()
    {
        // create the database
        $this->executor->execute("CREATE TABLE Dogs (Id INT NOT NULL IDENTITY(1,1) PRIMARY KEY, Breed VARCHAR(50) null, Name VARCHAR(50) null, Age INTEGER null, Weight FLOAT NULL)");
        // $this->executor->execute("ALTER DATABASE tempdb SET ALLOW_SNAPSHOT_ISOLATION ON");
        // $this->executor->execute("ALTER DATABASE tempdb SET READ_COMMITTED_SNAPSHOT ON");
    }

    public function deleteDatabase()
    {
        $this->executor->execute('drop table Dogs;');
    }

    public function testGetDate() {
        $data = $this->executor->getScalar("SELECT FORMAT(CONVERT(date, '2018-07-26 20:02:03'), 'yyyy-MM-dd') ");
        $this->assertEquals("2018-07-26", $data);

        $data = $this->executor->getScalar("SELECT FORMAT(CONVERT(datetime, '2018-07-26 20:02:03'), 'yyyy-MM-dd HH:mm:ss')");
        $this->assertEquals("2018-07-26 20:02:03", $data);
    }

    public function testDontParseParam()
    {
        $newUri = $this->executor->getDriver()->getUri()->withQueryKeyValue(DbPdoDriver::DONT_PARSE_PARAM, "")->withScheme("pdo");
        $newConn = Factory::getDbInstance($newUri);
        $it = DatabaseExecutor::using($newConn)->getIterator('select Id, Breed, Name, Age from Dogs where id = :field', ["field" => 1]);
        $this->assertCount(1, $it->toArray());
    }

    public function testDontParseParam_3() {
        $this->expectException(PDOException::class);

        parent::testDontParseParam_3();
    }

    public function testCheckInitialCommands()
    {
        $this->assertEquals(256, $this->executor->getScalar("SELECT 256 & @@OPTIONS")); // QUOTED_IDENTIFIER
        $this->assertEquals(8, $this->executor->getScalar('SELECT 8 & @@OPTIONS')); // ANSI_WARNINGS
        $this->assertEquals(16, $this->executor->getScalar('SELECT 16 & @@OPTIONS')); // ANSI_PADDING
        $this->assertEquals(32, $this->executor->getScalar('SELECT 32 & @@OPTIONS')); // ANSI_NULLS
        $this->assertEquals(4096, $this->executor->getScalar('SELECT 4096 & @@OPTIONS')); // CONCAT_NULL_YIELDS_NULL
    }

    public function testTwoDifferentTransactions()
    {
        $this->markTestSkipped('SQL Server table locking prevents concurrent transaction testing');
    }
}
