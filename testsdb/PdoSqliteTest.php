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
}
