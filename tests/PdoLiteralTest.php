<?php

namespace Test;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\PdoLiteral;
use Override;
use PDOException;
use TestDb\BasePdo;


class PdoLiteralTest extends BasePdo
{

    #[Override]
    protected function createInstance()
    {
        $dbDriver = new PdoLiteral("sqlite::memory:");
        DatabaseExecutor::using($dbDriver)
            ->execute("CREATE TABLE Dogs (Id INTEGER NOT NULL PRIMARY KEY, Breed VARCHAR(50), Name VARCHAR(50), Age INTEGER, Weight NUMERIC(10,2))");

        return $dbDriver;
    }

    #[Override]
    protected function createDatabase()
    {
        //create the database
    }

    #[Override]
    public function deleteDatabase()
    {
        // Do nothing. Executing in memory.
    }

    #[Override]
    public function testGetAllFields()
    {
        $this->markTestSkipped('SQLite does not support getAllFields() method');
    }

    #[Override]
    public function testGetDate()
    {
        $this->markTestSkipped('Date formatting test not applicable for SQLite in-memory database');
    }

    #[Override]
    public function testDontParseParam()
    {
        // Ignoring because it is using a connection into the memory.
        $this->markTestSkipped('Parameter parsing test not applicable for in-memory database connections');
    }

    #[Override]
    public function testDontParseParam_2()
    {
        // Ignoring because it is using a connection into the memory.
        $this->markTestSkipped('Parameter parsing test not applicable for in-memory database connections');
    }

    #[Override]
    public function testDontParseParam_3()
    {
        // Ignoring because it is using a connection into the memory.
        $this->markTestSkipped('Parameter parsing test not applicable for in-memory database connections');
    }

    #[Override]
    public function testReconnect()
    {
        $this->assertFalse($this->dbDriver->reconnect());
        $this->assertTrue($this->dbDriver->reconnect(true));

        // The connection is on memory. It means when reconnect will be in an empty DB
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/no such table/');
        $executor = DatabaseExecutor::using($this->dbDriver)
            ->getIterator('select Id, Breed, Name, Age from Dogs where id = 1');
    }

    #[Override]
    public function testTwoDifferentTransactions()
    {
        $this->markTestSkipped('In-memory databases cannot support transactions across different connections');
    }
}
