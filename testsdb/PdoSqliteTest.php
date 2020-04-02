<?php

namespace TestsDb\AnyDataset;

use ByJG\AnyDataset\Db\Factory;
use ByJG\Util\Uri;

require_once 'BasePdo.php';

class PdoSqliteTest extends BasePdo
{

    protected function createInstance()
    {
        $uri = Uri::getInstanceFromString("sqlite:///tmp/test.db")
            ->withQueryKeyValue("stmtcache", "true");

        $this->dbDriver = Factory::getDbInstance($uri);
    }

    protected function createDatabase()
    {
        //create the database
        $this->dbDriver->execute("CREATE TABLE Dogs (Id INTEGER PRIMARY KEY, Breed VARCHAR(50), Name VARCHAR(50), Age INTEGER)");
    }

    public function deleteDatabase()
    {
        unlink('/tmp/test.db');
    }

    public function testGetAllFields()
    {
        $this->markTestSkipped('SqlLite does not have this method');
    }


    public function testStatementCache()
    {
        $this->assertEquals("true", $this->dbDriver->getUri()->getQueryPart("stmtcache"));
        $this->assertEquals(2, $this->dbDriver->getCountStmtCache()); // because of createDatabase() and populateData()

        $i = 3;
        while ($i<=10) {
            $it = $this->dbDriver->getIterator("select $i as name");
            $this->assertEquals($i, $this->dbDriver->getCountStmtCache()); // because of createDatabase() and populateData()
            $this->assertEquals([["name" => $i]], $it->toArray());
            $i++;
        }

        $it = $this->dbDriver->getIterator("select 20 as name");
        $this->assertEquals(10, $this->dbDriver->getCountStmtCache()); // because of createDatabase() and populateData()
        $this->assertEquals([["name" => 20]], $it->toArray());

        $it = $this->dbDriver->getIterator("select 30 as name");
        $this->assertEquals(10, $this->dbDriver->getCountStmtCache()); // because of createDatabase() and populateData()
        $this->assertEquals([["name" => 30]], $it->toArray());
    }
}
