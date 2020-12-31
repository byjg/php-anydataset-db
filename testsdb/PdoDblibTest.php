<?php

namespace TestsDb\AnyDataset;

use ByJG\AnyDataset\Db\Factory;

require_once 'BasePdo.php';

class PdoDblibTest extends BasePdo
{

    protected function createInstance()
    {
        $host = getenv('MSSQL_TEST_HOST');
        if (empty($host)) {
            $host = "127.0.0.1";
        }
        $password = getenv('MSSQL_PASSWORD');
        if (empty($password)) {
            $password = 'Pa55word';
        }

        $this->dbDriver = Factory::getDbRelationalInstance("dblib://sa:$password@$host/tempdb");
    }

    protected function createDatabase()
    {
        //create the database
        $this->dbDriver->execute("CREATE TABLE Dogs (Id INT NOT NULL IDENTITY(1,1) PRIMARY KEY, Breed VARCHAR(50), Name VARCHAR(50), Age INTEGER)");
    }

    public function deleteDatabase()
    {
        $this->dbDriver->execute('drop table Dogs;');
    }
}
