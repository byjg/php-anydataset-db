<?php

namespace TestDb;

use ByJG\AnyDataset\Db\DbPdoDriver;
use ByJG\AnyDataset\Db\Factory;

class PdoDblibTest extends BasePdo
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

        return Factory::getDbRelationalInstance("dblib://sa:$password@$host/tempdb");
    }

    protected function createDatabase()
    {
        // create the database
        $this->dbDriver->execute("CREATE TABLE Dogs (Id INT NOT NULL IDENTITY(1,1) PRIMARY KEY, Breed VARCHAR(50) null, Name VARCHAR(50) null, Age INTEGER null, Weight FLOAT NULL)");
        // $this->dbDriver->execute("ALTER DATABASE tempdb SET ALLOW_SNAPSHOT_ISOLATION ON");
        // $this->dbDriver->execute("ALTER DATABASE tempdb SET READ_COMMITTED_SNAPSHOT ON");
    }

    public function deleteDatabase()
    {
        $this->dbDriver->execute('drop table Dogs;');
    }

    public function testGetDate() {
        $data = $this->dbDriver->getScalar("SELECT FORMAT(CONVERT(date, '2018-07-26 20:02:03'), 'yyyy-MM-dd') ");
        $this->assertEquals("2018-07-26", $data);

        $data = $this->dbDriver->getScalar("SELECT FORMAT(CONVERT(datetime, '2018-07-26 20:02:03'), 'yyyy-MM-dd HH:mm:ss')");
        $this->assertEquals("2018-07-26 20:02:03", $data);
    }

    public function testDontParseParam()
    {
        $newUri = $this->dbDriver->getUri()->withQueryKeyValue(DbPdoDriver::DONT_PARSE_PARAM, "")->withScheme("pdo");
        $newConn = Factory::getDbInstance($newUri);
        $newConn->getIterator('select Id, Breed, Name, Age from Dogs where id = :field', [ "field" => 1 ]);
    }

    public function testDontParseParam_3() {
        $this->expectException(\PDOException::class);

        parent::testDontParseParam_3();
    }

    public function testCheckInitialCommands()
    {
        $this->assertEquals(256, $this->dbDriver->getScalar("SELECT 256 & @@OPTIONS")); // QUOTED_IDENTIFIER
        $this->assertEquals(8, $this->dbDriver->getScalar('SELECT 8 & @@OPTIONS')); // ANSI_WARNINGS
        $this->assertEquals(16, $this->dbDriver->getScalar('SELECT 16 & @@OPTIONS')); // ANSI_PADDING
        $this->assertEquals(32, $this->dbDriver->getScalar('SELECT 32 & @@OPTIONS')); // ANSI_NULLS
        $this->assertEquals(4096, $this->dbDriver->getScalar('SELECT 4096 & @@OPTIONS')); // CONCAT_NULL_YIELDS_NULL
    }

    public function testTwoDifferentTransactions()
    {
        $this->markTestSkipped('SQLServer locks the table make the test inviable');
    }
}
