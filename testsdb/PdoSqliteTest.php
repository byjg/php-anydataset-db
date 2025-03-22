<?php

namespace TestDb;

use ByJG\AnyDataset\Db\Factory;
use ByJG\Util\Uri;


class PdoSqliteTest extends BasePdo
{
    protected $host;

    protected function createInstance()
    {
        $this->host = getenv('SQLITE_TEST_HOST');
        if (empty($host)) {
            $this->host = "/tmp/test.db";
        }

        $uri = Uri::getInstanceFromString("sqlite://" . $this->host);

        return Factory::getDbInstance($uri);
    }

    protected function createDatabase()
    {
        //create the database
        $this->dbDriver->execute("CREATE TABLE Dogs (Id INTEGER NOT NULL PRIMARY KEY, Breed VARCHAR(50), Name VARCHAR(50), Age INTEGER, Weight NUMERIC(10,2))");
    }

    public function deleteDatabase()
    {
        unlink($this->host);
    }

    public function testGetAllFields()
    {
        $this->markTestSkipped('SQLite does not support getAllFields() method');
    }

    public function testGetDate() {
        $data = $this->dbDriver->getScalar("SELECT DATE('2018-07-26') ");
        $this->assertEquals("2018-07-26", $data);

        $data = $this->dbDriver->getScalar("SELECT DATETIME('2018-07-26 20:02:03') ");
        $this->assertEquals("2018-07-26 20:02:03", $data);
    }
}
