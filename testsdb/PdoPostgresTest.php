<?php

namespace TestDb;

use ByJG\AnyDataset\Db\Factory;

class PdoPostgresTest extends BasePdo
{

    protected function createInstance()
    {
        $host = getenv('PSQL_TEST_HOST');
        if (empty($host)) {
            $host = "127.0.0.1";
        }
        $password = getenv('PSQL_PASSWORD');
        if (empty($password)) {
            $password = 'password';
        }
        if ($password == '.') {
            $password = "";
        }

        $dbDriver = Factory::getDbInstance("pgsql://postgres:$password@$host");
        $exists = $dbDriver->getScalar('select count(1) from pg_catalog.pg_database where datname = \'test\'');
        if ($exists == 0) {
            $dbDriver->execute('CREATE DATABASE test');
        }
        return Factory::getDbInstance("pgsql://postgres:$password@$host/test");
    }

    protected function createDatabase()
    {
        //create the database
        $this->dbDriver->execute("CREATE TABLE Dogs (Id SERIAL PRIMARY KEY, Breed VARCHAR(50), Name VARCHAR(50), Age INTEGER, Weight NUMERIC(10,2))");
    }

    public function deleteDatabase()
    {
        $this->dbDriver->execute('drop table Dogs;');
    }

    public function testGetDate() {
        $data = $this->dbDriver->getScalar("SELECT CAST('2018-07-26' AS DATE) ");
        $this->assertEquals("2018-07-26", $data);

        $data = $this->dbDriver->getScalar("SELECT CAST('2018-07-26 20:02:03' AS TIMESTAMP) ");
        $this->assertEquals("2018-07-26 20:02:03", $data);
    }

    public function testDontParseParam_3() {
        $this->expectException(\PDOException::class);
        
        parent::testDontParseParam_3();
    }

    public function testGetMetadata()
    {
        $metadata = $this->dbDriver->getDbHelper()->getTableMetadata($this->dbDriver, 'Dogs');

        foreach ($metadata as $key => $field) {
            unset($metadata[$key]['dbType']);
        }

        $this->assertEquals([
            'id' => [
                'name' => 'id',
                'required' => true,
                'default' => "nextval('dogs_id_seq'::regclass)",
                'phpType' => 'integer',
                'length' => null,
                'precision' => null,
            ],
            'breed' => [
                'name' => 'breed',
                'required' => false,
                'default' => null,
                'phpType' => 'string',
                'length' => 50,
                'precision' => null,
            ],
            'name' => [
                'name' => 'name',
                'required' => false,
                'default' => null,
                'phpType' => 'string',
                'length' => 50,
                'precision' => null,
            ],
            'age' => [
                'name' => 'age',
                'required' => false,
                'default' => null,
                'phpType' => 'integer',
                'length' => null,
                'precision' => null,
            ],
            'weight' => [
                'name' => 'weight',
                'required' => false,
                'default' => null,
                'phpType' => 'float',
                'length' => 10,
                'precision' => 2,
            ],
        ], $metadata);
    }
}
