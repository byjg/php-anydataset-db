<?php

namespace TestsDb\AnyDataset;

use ByJG\AnyDataset\Db\Factory;

require_once 'BasePdo.php';

class PdoMySqlest extends BasePdo
{

    protected function createInstance()
    {
        $this->escapeQuote = "\'";

        $host = getenv('MYSQL_TEST_HOST');
        if (empty($host)) {
            $host = "127.0.0.1";
        }
        $password = getenv('MYSQL_PASSWORD');
        if (empty($password)) {
            $password = 'password';
        }
        if ($password == '.') {
            $password = "";
        }

        $this->dbDriver = Factory::getDbRelationalInstance("mysql://root:$password@$host");
        $this->dbDriver->execute('CREATE DATABASE IF NOT EXISTS test');
        $this->dbDriver = Factory::getDbRelationalInstance("mysql://root:$password@$host/test");
    }

    protected function createDatabase()
    {
        //create the database
        $this->dbDriver->execute("CREATE TABLE Dogs (Id INTEGER PRIMARY KEY auto_increment, Breed VARCHAR(50), Name VARCHAR(50), Age INTEGER)");
    }

    public function deleteDatabase()
    {
        $this->dbDriver->execute('drop table Dogs;');
    }
}
