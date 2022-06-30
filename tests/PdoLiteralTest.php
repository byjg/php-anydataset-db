<?php

namespace TestsDb\AnyDataset;

use ByJG\AnyDataset\Db\PdoLiteral;

require_once __DIR__ . '/../testsdb/BasePdo.php';

class PdoLiteralTest extends BasePdo
{

    protected function createInstance()
    {
        $this->dbDriver = new PdoLiteral("sqlite::memory:");
    }

    protected function createDatabase()
    {
        //create the database
        $this->dbDriver->execute("CREATE TABLE Dogs (Id INTEGER PRIMARY KEY, Breed VARCHAR(50), Name VARCHAR(50), Age INTEGER)");
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
}