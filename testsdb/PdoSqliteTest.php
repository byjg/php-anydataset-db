<?php

namespace TestsDb\AnyDataset;

use ByJG\AnyDataset\Db\Factory;
use ByJG\Util\Uri;

require_once 'BasePdo.php';

class PdoSqliteTest extends BasePdo
{
    protected $host;

    protected function createInstance()
    {
        $this->host = getenv('SQLITE_TEST_HOST');
        if (empty($host)) {
            $this->host = "/tmp/test.db";
        }

        $uri = Uri::getInstanceFromString("sqlite://" . $this->host)
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
        unlink($this->host);
    }

    public function testGetAllFields()
    {
        $this->markTestSkipped('Skipped: SqlLite does not support get all fields');
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
