<?php

namespace TestDb;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\Metadata\ColumnMetadata;
use PDOException;

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
        $executor = DatabaseExecutor::using($dbDriver);
        $exists = $executor->getScalar('select count(1) from pg_catalog.pg_database where datname = \'test\'');
        if ($exists == 0) {
            $executor->execute('CREATE DATABASE test');
        }
        return Factory::getDbInstance("pgsql://postgres:$password@$host/test");
    }

    protected function createDatabase()
    {
        //create the database
        $this->executor->execute("CREATE TABLE Dogs (Id SERIAL PRIMARY KEY, Breed VARCHAR(50), Name VARCHAR(50), Age INTEGER, Weight NUMERIC(10,2))");
    }

    public function deleteDatabase()
    {
        $this->executor->execute('drop table Dogs;');
    }

    public function testGetDate() {
        $data = $this->executor->getScalar("SELECT CAST('2018-07-26' AS DATE) ");
        $this->assertEquals("2018-07-26", $data);

        $data = $this->executor->getScalar("SELECT CAST('2018-07-26 20:02:03' AS TIMESTAMP) ");
        $this->assertEquals("2018-07-26 20:02:03", $data);
    }

    public function testDontParseParam_3() {
        $this->expectException(PDOException::class);
        
        parent::testDontParseParam_3();
    }

    public function testGetMetadata()
    {
        $metadata = $this->executor->getHelper()->getTableMetadata($this->executor, 'Dogs');
        $this->assertContainsOnlyInstancesOf(ColumnMetadata::class, $metadata);
        $metadata = $this->metadataToArray($metadata);

        $this->assertEquals([
            'id' => [
                'name' => 'id',
                'required' => true,
                'default' => "nextval('dogs_id_seq'::regclass)",
                'phpType' => 'integer',
                'length' => null,
                'precision' => null,
                'primaryKey' => true,
                'autoIncrement' => true,
                'generated' => false,
            ],
            'breed' => [
                'name' => 'breed',
                'required' => false,
                'default' => null,
                'phpType' => 'string',
                'length' => 50,
                'precision' => null,
                'primaryKey' => false,
                'autoIncrement' => false,
                'generated' => false,
            ],
            'name' => [
                'name' => 'name',
                'required' => false,
                'default' => null,
                'phpType' => 'string',
                'length' => 50,
                'precision' => null,
                'primaryKey' => false,
                'autoIncrement' => false,
                'generated' => false,
            ],
            'age' => [
                'name' => 'age',
                'required' => false,
                'default' => null,
                'phpType' => 'integer',
                'length' => null,
                'precision' => null,
                'primaryKey' => false,
                'autoIncrement' => false,
                'generated' => false,
            ],
            'weight' => [
                'name' => 'weight',
                'required' => false,
                'default' => null,
                'phpType' => 'float',
                'length' => 10,
                'precision' => 2,
                'primaryKey' => false,
                'autoIncrement' => false,
                'generated' => false,
            ],
        ], $metadata);
    }

    public function testGetMetadataOfAColumnWithoutLength()
    {
        // The type used to be NULL for a type with neither a length nor a precision
        $this->executor->execute("CREATE TABLE MetaTime (Id INTEGER NOT NULL PRIMARY KEY, CreatedAt TIMESTAMP)");
        try {
            $metadata = $this->executor->getHelper()->getTableMetadata($this->executor, 'MetaTime');

            $this->assertSame('timestamp without time zone', $metadata['createdat']->dbType);
        } finally {
            $this->executor->execute("DROP TABLE MetaTime");
        }
    }
}
