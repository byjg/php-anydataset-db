<?php

namespace Test;

use ByJG\AnyDataset\Db\PdoLiteral;
use TestDb\BasePdo;


class PdoLiteralTest extends BasePdo
{

    protected function createInstance()
    {
        $this->dbDriver = new PdoLiteral("sqlite::memory:");
    }

    protected function createDatabase()
    {
        //create the database
        $this->dbDriver->execute("CREATE TABLE Dogs (Id INTEGER NOT NULL PRIMARY KEY, Breed VARCHAR(50), Name VARCHAR(50), Age INTEGER, Weight NUMERIC(10,2))");
    }

    public function deleteDatabase()
    {
        // Do nothing. Executing in memory.
    }

    public function testGetAllFields()
    {
        $this->markTestSkipped('SqlLite does not have this method');
    }

    public function testGetDate()
    {
        $this->markTestSkipped('Do not use here');
    }

    public function testDontParseParam()
    {
        // Ignoring because is using a connection into the memory.
        $this->markTestSkipped();
    }

    public function testDontParseParam_2()
    {
        // Ignoring because is using a connection into the memory.
        $this->markTestSkipped();
    }

    public function testDontParseParam_3()
    {
        // Ignoring because is using a connection into the memory.
        $this->markTestSkipped();
    }

    public function testReconnect()
    {
        $this->assertFalse($this->dbDriver->reconnect());
        $this->assertTrue($this->dbDriver->reconnect(true));

        // The connection is on memory, it means, when reconnect will be in an empty DB
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessageMatches('/no such table/');
        $iterator = $this->dbDriver->getIterator('select Id, Breed, Name, Age from Dogs where id = 1');
    }
}
