<?php

namespace TestDb;

use ByJG\AnyDataset\Core\Exception\NotImplementedException;
use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\DbPdoDriver;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\Exception\TransactionNotStartedException;
use ByJG\AnyDataset\Db\Exception\TransactionStartedException;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\AnyDataset\Db\SqlStatement;
use ByJG\Cache\Psr16\ArrayCacheEngine;
use ByJG\Serializer\PropertyHandler\PropertyNameMapper;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\Models\DogEntity;
use Test\Models\DogEntityComplex;
use Test\Models\Dogs;

abstract class BasePdo extends TestCase
{

    /**
     * @var DbDriverInterface
     */
    protected $dbDriver;

    protected $escapeQuote = "''";

    protected $floatSize = 10;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        $this->dbDriver = $this->createInstance();
        $this->createDatabase();
        $this->populateData();
    }

    protected function createInstance()
    {
        throw new NotImplementedException('Implement createInstance method');
    }

    protected function populateData()
    {
        //insert some data...
        $array = $this->allData();
        $sqlStatement = new SqlStatement("INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES (:breed, :name, :age, :weight);");
        foreach ($array as $param) {
            $this->dbDriver->execute(
                $sqlStatement,
                $param
            );
        }
    }

    abstract protected function createDatabase();

    abstract protected function deleteDatabase();

    public function tearDown(): void
    {
        $this->dbDriver->reconnect();
        $this->deleteDatabase();
    }

    protected function allData()
    {
        return [
            [
                'breed' => 'Mutt',
                'name' => 'Spyke',
                'age' => 8,
                'id' => 1,
                'weight' =>  8.5
            ],
            [
                'breed' => 'Brazilian Terrier',
                'name' => 'Sandy',
                'age' => 3,
                'id' => 2,
                'weight' =>  3.8
            ],
            [
                'breed' => 'Pincher',
                'name' => 'Lola',
                'age' => 1,
                'id' => 3,
                'weight' =>  1.2
            ]
        ];
    }

    public function testGetIterator()
    {
        $array = $this->allData();

        // Step 1
        $iterator = $this->dbDriver->getIterator('select * from Dogs');
        $this->assertEquals($array, $iterator->toArray());

        // Step 2
        $iterator = $this->dbDriver->getIterator('select * from Dogs');
        $i = 0;
        foreach ($iterator as $singleRow) {
            $this->assertEquals($array[$i++], $singleRow->toArray());
        }

        // Step 3
        $iterator = $this->dbDriver->getIterator('select * from Dogs');
        $i = 0;
        while ($iterator->valid()) {
            $singleRow = $iterator->current();
            $this->assertEquals($array[$i++], $singleRow->toArray());
            $iterator->next();
        }

        $this->assertFalse($iterator->isCursorOpen());
    }

    public function testGetIteratorSqlStatement()
    {
        $array = $this->allData();

        // Step 1
        $sqlStatement = new SqlStatement('select * from Dogs');
        $iterator = $this->dbDriver->getIterator($sqlStatement);
        $this->assertEquals($array, $iterator->toArray());

        // Step 2
        $sqlStatement = new SqlStatement('select * from Dogs where id = :id', ['id' => 1]);
        $iterator = $this->dbDriver->getIterator($sqlStatement);
        $this->assertEquals([$array[0]], $iterator->toArray());

        // Step 2
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 2]);
        $this->assertEquals([$array[1]], $iterator->toArray());
    }

    public function testGetIteratorSqlStatementAndPartialParams()
    {
        $array = $this->allData();

        $sqlStatement = new SqlStatement('select * from Dogs where age <= :age and name = :name', ['age' => 5]);

        $iterator = $this->dbDriver->getIterator($sqlStatement, ['name' => 'Spyke']);
        $this->assertCount(0, $iterator->toArray());

        $iterator = $this->dbDriver->getIterator($sqlStatement, ['name' => 'Sandy']);
        $this->assertCount(1, $iterator->toArray());
    }


    public function testGetIteratorWithEntityClass()
    {
        $array = $this->allData();

        // Get iterator with entity class
        $iterator = $this->dbDriver->getIterator('select * from Dogs', entityClass: Dogs::class);

        // Verify we get objects of the correct type
        $i = 0;
        foreach ($iterator as $singleRow) {
            $entity = $singleRow->entity();
            $this->assertInstanceOf(Dogs::class, $entity);

            // Verify properties were populated correctly
            $this->assertEquals($array[$i]['id'], $entity->id);
            $this->assertEquals($array[$i]['name'], $entity->name);
            $this->assertEquals($array[$i]['breed'], $entity->breed);
            $this->assertEquals($array[$i]['weight'], $entity->weight);

            $i++;
        }

        // Verify we got all the expected rows
        $this->assertEquals(count($array), $i);
    }

    public function testGetIteratorWithEntityClassAndSqlStatement()
    {
        $array = $this->allData();

        $sqlStatement = new SqlStatement('select * from Dogs');

        // Get iterator with entity class
        $iterator = $this->dbDriver->getIterator($sqlStatement, entityClass: Dogs::class);

        // Verify we get objects of the correct type
        $i = 0;
        foreach ($iterator as $singleRow) {
            $entity = $singleRow->entity();
            $this->assertInstanceOf(Dogs::class, $entity);

            // Verify properties were populated correctly
            $this->assertEquals($array[$i]['id'], $entity->id);
            $this->assertEquals($array[$i]['name'], $entity->name);
            $this->assertEquals($array[$i]['breed'], $entity->breed);
            $this->assertEquals($array[$i]['weight'], $entity->weight);

            $i++;
        }

        // Verify we got all the expected rows
        $this->assertEquals(count($array), $i);
    }

    public function testExecute()
    {
        $this->dbDriver->execute(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Cat', 'Doris', 7);"
        );

        $sqlStatement = new SqlStatement("INSERT INTO Dogs (Breed, Name, Age) VALUES (:breed, :name, :age);");
        $this->dbDriver->execute(
            $sqlStatement,
            [
                "breed" => "Cat2",
                "name" => "Doris2",
                "age" => 8
            ]
        );

        $idInserted = $this->dbDriver->getScalar("select id from Dogs where name = 'Doris'");
        $this->assertEquals(4, $idInserted);

        $idInserted = $this->dbDriver->getScalar("select id from Dogs where name = 'Doris2'");
        $this->assertEquals(5, $idInserted);
    }

    public function testExecuteAndGetId()
    {
        $idInserted = $this->dbDriver->executeAndGetId(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Cat', 'Doris', 7);"
        );

        $this->assertEquals(4, $idInserted);

        $sqlStatement = new SqlStatement("INSERT INTO Dogs (Breed, Name, Age) VALUES (:breed, :name, :age);");
        $idInserted = $this->dbDriver->executeAndGetId(
            $sqlStatement,
            [
                "breed" => "Cat2",
                "name" => "Doris2",
                "age" => 8
            ]
        );
        $this->assertEquals(5, $idInserted);
    }

    public function testGetAllFields()
    {
        $allFields = $this->dbDriver->getAllFields('Dogs');

        $this->assertEquals(
            [
                'id',
                'breed',
                'name',
                'age',
                'weight'
            ],
            $allFields
        );
    }

    public function testGetScalar()
    {
        $this->assertEquals(
            1,
            $this->dbDriver->getScalar('select count(*) from Dogs where Id = :id', ['id' => 2])
        );

        $this->assertEquals(
            2,
            $this->dbDriver->getScalar('select Id from Dogs where Id = :id', ['id' => 2])
        );

        $this->assertEquals(
            3,
            $this->dbDriver->getScalar('select count(*) from Dogs')
        );

        $this->assertFalse(
            $this->dbDriver->getScalar('select Id from Dogs where Id = :id', ['id' => 9999])
        );
    }

    public function testGetScalarWithSqlStatement()
    {
        $sqlStatement = new SqlStatement('select Id from Dogs where Id = :id', ['id' => 2]);
        $this->assertEquals(
            2,
            $this->dbDriver->getScalar($sqlStatement)
        );

        $this->assertEquals(
            1,
            $this->dbDriver->getScalar($sqlStatement, ['id' => 1])
        );

        $this->assertFalse(
            $this->dbDriver->getScalar($sqlStatement, ['id' => 9999])
        );
    }

    public function testMultipleRowset()
    {
        if (!$this->dbDriver->isSupportMultiRowset()) {
            $this->markTestSkipped('This database driver does not support multiple result sets');
        }

        $sql = "INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES ('Cat', 'Doris', 7, 4.2); " .
            "INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES ('Dog', 'Lolla', 1, 1.4); ";

        $idInserted = $this->dbDriver->executeAndGetId($sql);

        $this->assertEquals(5, $idInserted);

        $this->assertEquals(
            'Doris',
            $this->dbDriver->getScalar('select name from Dogs where Id = :id', ['id' => 4])
        );

        $this->assertEquals(
            'Lolla',
            $this->dbDriver->getScalar('select name from Dogs where Id = :id', ['id' => 5])
        );
    }

    public function testParameterInsideQuotes()
    {
        $sql = "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Cat', 'a:Doris', 7); ";
        $id = $this->dbDriver->executeAndGetId($sql);
        $this->assertEquals(4, $id);

        $sql = "select id from Dogs where name = 'a:Doris'";
        $id = $this->dbDriver->getScalar($sql);
        $this->assertEquals(4, $id);
    }

    public function testInsertSpecialChars()
    {
        $this->dbDriver->execute(
            "INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES ('Dog', '€ Sign Pètit Pannô', 6, 3.2);"
        );

        $iterator = $this->dbDriver->getIterator('select Id, Breed, Name, Age, Weight from Dogs where id = 4');
        $row = $iterator->toArray();
        $this->assertFalse($iterator->isCursorOpen());

        $this->assertEquals(4, $row[0]["id"]);
        $this->assertEquals('Dog', $row[0]["breed"]);
        $this->assertEquals('€ Sign Pètit Pannô', $row[0]["name"]);
        $this->assertEquals(6, $row[0]["age"]);
        $this->assertEquals(3.2, $row[0]["weight"]);
    }

    public function testEscapeQuote()
    {
        $escapeQuote = $this->escapeQuote;

        $this->dbDriver->execute(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Dog', 'Puppy{$escapeQuote}s Master', 6);"
        );

        $iterator = $this->dbDriver->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();
        $this->assertFalse($iterator->isCursorOpen());

        $this->assertEquals(4, $row[0]["id"]);
        $this->assertEquals('Dog', $row[0]["breed"]);
        $this->assertEquals('Puppy\'s Master', $row[0]["name"]);
        $this->assertEquals(6, $row[0]["age"]);
    }

    public function testEscapeQuoteWithParam()
    {
        $this->dbDriver->execute(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES (:breed, :name, :age);",
            [
                "breed" => 'Dog',
                "name" => "Puppy's Master",
                "age" => 6
            ]
        );

        $iterator = $this->dbDriver->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();
        $this->assertFalse($iterator->isCursorOpen());

        $this->assertEquals(4, $row[0]["id"]);
        $this->assertEquals('Dog', $row[0]["breed"]);
        $this->assertEquals('Puppy\'s Master', $row[0]["name"]);
        $this->assertEquals(6, $row[0]["age"]);
    }

    public function testEscapeQuoteWithMixedParam()
    {
        $escapeQuote = $this->escapeQuote;

        $this->dbDriver->execute(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES (:breed, 'Puppy{$escapeQuote}s Master', :age);",
            [
                "breed" => 'Dog',
                "age" => 6
            ]
        );

        $iterator = $this->dbDriver->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();
        $this->assertFalse($iterator->isCursorOpen());

        $this->assertEquals(4, $row[0]["id"]);
        $this->assertEquals('Dog', $row[0]["breed"]);
        $this->assertEquals('Puppy\'s Master', $row[0]["name"]);
        $this->assertEquals(6, $row[0]["age"]);
    }

    public function testGetBuggyUT8()
    {
        $this->dbDriver->execute(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Dog', 'FÃ©lix', 6);"
        );

        $iterator = $this->dbDriver->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();
        $this->assertFalse($iterator->isCursorOpen());

        $this->assertEquals(4, $row[0]["id"]);
        $this->assertEquals('Dog', $row[0]["breed"]);
        $this->assertEquals('FÃ©lix', $row[0]["name"]);
        $this->assertEquals(6, $row[0]["age"]);
    }

    public function testDontParseParam()
    {
        $newUri = $this->dbDriver->getUri()->withQueryKeyValue(DbPdoDriver::DONT_PARSE_PARAM, "");
        $newConn = Factory::getDbInstance($newUri);
        $it = $newConn->getIterator('select Id, Breed, Name, Age from Dogs where id = :field', ["field" => 1]);
        $this->assertCount(1, $it->toArray());
        $this->assertFalse($it->isCursorOpen());
    }

    public function testDontParseParam_2()
    {
        $it = $this->dbDriver->getIterator('select Id, Breed, Name, Age from Dogs where id = :field');
        $this->assertCount(0, $it->toArray());
    }

    public function testDontParseParam_3()
    {
        $newUri = $this->dbDriver->getUri()->withQueryKeyValue(DbPdoDriver::DONT_PARSE_PARAM, "");
        $newConn = Factory::getDbInstance($newUri);
        $it = $newConn->getIterator('select Id, Breed, Name, Age from Dogs where id = :field');
        $this->assertCount(0, $it->toArray());
    }


    public function testCachedResults()
    {
        // Check with no cache at all
        $iterator = $this->dbDriver->getIterator('select * from Dogs where id = :id', ['id' => 1]);
        $this->assertEquals(
            [
                [ 'id'=> 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "weight" => 8.5],
            ],
            $iterator->toArray()
        );

        $cacheEngine = new ArrayCacheEngine();
        // Get the first from Db and then cache it;
        $sqlStatement = new SqlStatement('select * from Dogs where id = :id');
        $sqlStatement = $sqlStatement->withCache($cacheEngine, 'dogs', 60);
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 1]);
        $this->assertEquals(
            [
                ['id' => 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "weight" => 8.5],
            ],
            $iterator->toArray()
        );

        // Remove it from DB (Still in cache) - Execute don't use cache
        $this->dbDriver->execute("delete from Dogs where id = :id", ['id' => 1]);

        // Try get from cache
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 1]);
        $this->assertEquals(
            [
                ['id' => 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "weight" => 8.5],
            ],
            $iterator->toArray()
        );
    }

    public function testCachedResultsNotFound()
    {
        $cacheEngine = new ArrayCacheEngine();

        // Get the first from Db and then cache it;
        $sqlStatement = SqlStatement::from('select * from Dogs where id = :id')
            ->withCache($cacheEngine, 'dogs_id_test', 60);
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEquals(
            [],
            $iterator->toArray()
        );

        // Get the second from Db and then cache it, since is the same statement
        // However, the cache key is different because of the different param;
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 1]);
        $this->assertEquals(
            [
                ['id' => 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "weight" => 8.5],
            ],
            $iterator->toArray()
        );


        // Update Record
        $id = $this->dbDriver->executeAndGetId("INSERT INTO Dogs (Breed, Name, Age) VALUES (:breed, :name, :age);", ["breed" => "Cat", "name" => "Doris", "age" => 6]);
        $this->assertEquals(4, $id);
        $this->dbDriver->execute("update Dogs set age = 15 where id = 1");

        // Try get from cache (should have the same result from before)
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 1]);
        $this->assertEquals(
            [
                ['id' => 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "weight" => 8.5],
            ],
            $iterator->toArray()
        );

        // Try get from cache (should have the same result from before)
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEquals(
            [],
            $iterator->toArray()
        );

        // Create a new Statement with no cache
        $sqlStatement = new SqlStatement('select * from Dogs where id = :id');
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEquals(
            [
                ['id' => 4, 'breed' => "Cat", 'name' => 'Doris', "age" => 6, "weight" => null],
            ],
            $iterator->toArray()
        );
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 1]);
        $this->assertEquals(
            [
                ['id' => 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 15, "weight" => 8.5],
            ],
            $iterator->toArray()
        );
    }

    public function testGetDate() {
        throw new NotImplementedException("Needs to be implemented for each database");
    }

    public function testGetMetadata()
    {
        $metadata = $this->dbDriver->getDbHelper()->getTableMetadata($this->dbDriver, 'Dogs');

        foreach ($metadata as $key => $field) {
            unset($metadata[$key]['dbType']);
        }

        $this->assertEquals($this->getExpectedMetadata(), $metadata);
    }

    protected function getExpectedMetadata()
    {
        return [
            'id' => [
                'name' => 'Id',
                'required' => true,
                'default' => null,
                'phpType' => 'integer',
                'length' => null,
                'precision' => null,
            ],
            'breed' => [
                'name' => 'Breed',
                'required' => false,
                'default' => null,
                'phpType' => 'string',
                'length' => 50,
                'precision' => null,
            ],
            'name' => [
                'name' => 'Name',
                'required' => false,
                'default' => null,
                'phpType' => 'string',
                'length' => 50,
                'precision' => null,
            ],
            'age' => [
                'name' => 'Age',
                'required' => false,
                'default' => null,
                'phpType' => 'integer',
                'length' => null,
                'precision' => null,
            ],
            'weight' => [
                'name' => 'Weight',
                'required' => false,
                'default' => null,
                'phpType' => 'float',
                'length' => $this->floatSize,
                'precision' => 2,
            ],
        ];
    }

    public function testDisconnect()
    {
        $iterator = $this->dbDriver->getIterator('select Id, Breed, Name, Age from Dogs where id = 1');
        $row = $iterator->toArray();
        $this->assertEquals(1, $row[0]["id"]);

        $this->dbDriver->disconnect();

        $this->expectException(DbDriverNotConnected::class);
        $iterator = $this->dbDriver->getIterator('select Id, Breed, Name, Age from Dogs where id = 1');
    }

    public function testReconnect()
    {
        $this->assertFalse($this->dbDriver->reconnect());
        $this->assertTrue($this->dbDriver->reconnect(true));
        $iterator = $this->dbDriver->getIterator('select Id, Breed, Name, Age from Dogs where id = 1');
    }

    public function testCommitTransaction()
    {
        $this->assertFalse($this->dbDriver->hasActiveTransaction());
        $this->assertNull($this->dbDriver->activeIsolationLevel());
        $this->dbDriver->beginTransaction(IsolationLevelEnum::SERIALIZABLE);
        $this->assertTrue($this->dbDriver->hasActiveTransaction());
        $this->assertEquals(IsolationLevelEnum::SERIALIZABLE, $this->dbDriver->activeIsolationLevel());

        $idInserted = $this->dbDriver->executeAndGetId(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Cat', 'Doris', 7);"
        );
        $this->dbDriver->commitTransaction();
        $this->assertFalse($this->dbDriver->hasActiveTransaction());
        $this->assertNull($this->dbDriver->activeIsolationLevel());

        $this->assertEquals(4, $idInserted);

        $iterator = $this->dbDriver->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();

        $this->assertEquals(4, $row[0]["id"]);
        $this->assertEquals('Cat', $row[0]["breed"]);
        $this->assertEquals('Doris', $row[0]["name"]);
        $this->assertEquals(7, $row[0]["age"]);
    }

    public function testRollbackTransaction()
    {
        $this->assertFalse($this->dbDriver->hasActiveTransaction());
        $this->assertNull($this->dbDriver->activeIsolationLevel());
        $this->dbDriver->beginTransaction(IsolationLevelEnum::REPEATABLE_READ);
        $this->assertTrue($this->dbDriver->hasActiveTransaction());
        $this->assertEquals(IsolationLevelEnum::REPEATABLE_READ, $this->dbDriver->activeIsolationLevel());

        $idInserted = $this->dbDriver->executeAndGetId(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Cat', 'Doris', 7);"
        );
        $this->dbDriver->rollbackTransaction();
        $this->assertFalse($this->dbDriver->hasActiveTransaction());
        $this->assertNull($this->dbDriver->activeIsolationLevel());

        $this->assertEquals(4, $idInserted);

        $iterator = $this->dbDriver->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();

        $this->assertEmpty($row);
    }

    public function testCommitWithoutTransaction()
    {
        $this->expectException(TransactionNotStartedException::class);
        $this->dbDriver->commitTransaction();
    }

    public function testRollbackWithoutTransaction()
    {
        $this->expectException(TransactionNotStartedException::class);
        $this->dbDriver->rollbackTransaction();
    }

    public function testRequiresTransaction()
    {
        $this->assertFalse($this->dbDriver->hasActiveTransaction());
        $this->assertNull($this->dbDriver->activeIsolationLevel());
        $this->assertEquals(0, $this->dbDriver->remainingCommits());

        $this->dbDriver->beginTransaction(IsolationLevelEnum::READ_COMMITTED);
        $this->assertTrue($this->dbDriver->hasActiveTransaction());
        $this->assertEquals(IsolationLevelEnum::READ_COMMITTED, $this->dbDriver->activeIsolationLevel());
        $this->assertEquals(1, $this->dbDriver->remainingCommits());

        $this->dbDriver->requiresTransaction();
        $this->assertTrue($this->dbDriver->hasActiveTransaction());
        $this->assertEquals(IsolationLevelEnum::READ_COMMITTED, $this->dbDriver->activeIsolationLevel());
        $this->assertEquals(1, $this->dbDriver->remainingCommits());

        $this->dbDriver->commitTransaction();
        $this->assertFalse($this->dbDriver->hasActiveTransaction());
        $this->assertNull($this->dbDriver->activeIsolationLevel());
        $this->assertEquals(0, $this->dbDriver->remainingCommits());
    }

    public function testRequiresTransactionWithoutTransaction()
    {
        $this->expectException(TransactionNotStartedException::class);
        $this->dbDriver->requiresTransaction();
    }

    public function testBeginTransactionTwice()
    {
        $this->dbDriver->beginTransaction(IsolationLevelEnum::READ_UNCOMMITTED);
        $this->expectException(TransactionStartedException::class);
        try {
            $this->dbDriver->beginTransaction();
        } finally {
            $this->dbDriver->rollbackTransaction();
            $this->assertFalse($this->dbDriver->hasActiveTransaction());
            $this->assertNull($this->dbDriver->activeIsolationLevel());
            $this->assertEquals(0, $this->dbDriver->remainingCommits());
        }
    }

    public function testJoinTransaction()
    {
        $this->dbDriver->beginTransaction(IsolationLevelEnum::READ_UNCOMMITTED);
        $this->assertEquals(1, $this->dbDriver->remainingCommits());
        $this->assertEquals(IsolationLevelEnum::READ_UNCOMMITTED, $this->dbDriver->activeIsolationLevel());

        $this->dbDriver->beginTransaction(IsolationLevelEnum::READ_UNCOMMITTED, true);
        $this->assertEquals(2, $this->dbDriver->remainingCommits());
        $this->assertEquals(IsolationLevelEnum::READ_UNCOMMITTED, $this->dbDriver->activeIsolationLevel());

        $this->dbDriver->commitTransaction();
        $this->assertEquals(1, $this->dbDriver->remainingCommits());
        $this->assertTrue($this->dbDriver->hasActiveTransaction());
        $this->assertEquals(IsolationLevelEnum::READ_UNCOMMITTED, $this->dbDriver->activeIsolationLevel());

        $this->dbDriver->commitTransaction();
        $this->assertEquals(0, $this->dbDriver->remainingCommits());
        $this->assertFalse($this->dbDriver->hasActiveTransaction());
        $this->assertNull($this->dbDriver->activeIsolationLevel());
    }

    public function testTwoDifferentTransactions()
    {
        $dbDriver1 = $this->createInstance();
        $dbDriver2 = $this->createInstance();

        // Make sure there is no record in the database
        $iterator = $dbDriver1->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();
        $this->assertEmpty($row);
        $iterator = $dbDriver2->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();
        $this->assertEmpty($row);

        // Start a transaction on the first connection
        $dbDriver1->beginTransaction(IsolationLevelEnum::SERIALIZABLE);
        $idInserted = $dbDriver1->executeAndGetId(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Cat', 'Doris', 7);"
        );

        // Check if the record is there
        $iterator = $dbDriver1->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();
        $this->assertNotEmpty($row);

        // Check if the record is not there on the second connection (due to isolation level)
        $iterator = $dbDriver2->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();
        $this->assertEmpty($row);

        // Commit the transaction
        $dbDriver1->commitTransaction();

        // Check if the second transaction can read
        $iterator = $dbDriver2->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();
        $this->assertNotEmpty($row);
    }

    /**
     * @return void
     * @psalm-suppress UndefinedMethod
     */
    #[DataProvider('dataProviderPreFetch')]
    public function testPreFetchWhile(int $preFetch, array $rows, array $expected, array $expectedCursor)
    {
        $iterator = $this->dbDriver->getIterator('select * from Dogs', preFetch: $preFetch);

        $i = 0;
        while ($iterator->valid()) {
            $row = $iterator->current();
            $this->assertEquals($rows[$i], $row->toArray(), "Row $i");
            $this->assertEquals($i, $iterator->key(), "Key Row $i");
            $i++;
            $iterator->next();
        }
        $this->assertFalse($iterator->isCursorOpen());
    }

    /**
     * @psalm-suppress UndefinedMethod
     * @return void
     */
    #[DataProvider('dataProviderPreFetch')]
    public function testPreFetchForEach(int $preFetch, array $rows, array $expected, array $expectedCursor)
    {
        $iterator = $this->dbDriver->getIterator('select * from Dogs', preFetch: $preFetch);

        $i = 0;
        foreach ($iterator as $row) {
            $this->assertEquals($rows[$i], $row->toArray(), "Row[$preFetch] $i");
            $this->assertEquals($i, $iterator->key(), "Key Row[$preFetch] $i");
            $this->assertEquals($expected[$i], $iterator->getPreFetchBufferSize(), "PreFetchBufferSize Row[$preFetch] $i");
            $this->assertEquals($expectedCursor[$i], $iterator->isCursorOpen(), "CursorOpen Row[$preFetch] $i");
            $i++;
        }
        $this->assertFalse($iterator->isCursorOpen());
    }

    /**
     * @psalm-suppress UndefinedMethod
     * @return void
     */
    #[DataProvider('dataProviderPreFetch')]
    public function testPreFetchPhpIterator(int $preFetch, array $rows, array $expected, array $expectedCursor)
    {
        $iterator = $this->dbDriver->getIterator('select * from Dogs', preFetch: $preFetch);

        $i = 0;
        while ($iterator->valid()) {
            $row = $iterator->current();
            $this->assertEquals($rows[$i], $row->toArray(), "Row[$preFetch] $i");
            $this->assertEquals($i, $iterator->key(), "Key Row[$preFetch] $i");
            $this->assertEquals($expected[$i], $iterator->getPreFetchBufferSize(), "PreFetchBufferSize Row[$preFetch] $i");
            $this->assertEquals($expectedCursor[$i], $iterator->isCursorOpen(), "CursorOpen Row[$preFetch] $i");
            $i++;
            $iterator->next();
        }
        $this->assertFalse($iterator->isCursorOpen());
    }

    public static function dataProviderPreFetch()
    {
        $rows = [
            [
                'breed' => 'Mutt',
                'name' => 'Spyke',
                'age' => 8,
                'id' => 1,
                'weight' => 8.5
            ],
            [
                'breed' => 'Brazilian Terrier',
                'name' => 'Sandy',
                'age' => 3,
                'id' => 2,
                'weight' => 3.8
            ],
            [
                'breed' => 'Pincher',
                'name' => 'Lola',
                'age' => 1,
                'id' => 3,
                'weight' => 1.2
            ]
        ];


        return [
            [0, $rows, [1, 1, 1], [true, true, true]],
            [1, $rows, [1, 1, 1], [true, true, true]],
            [2, $rows, [2, 2, 1], [true, true, false]],
            [3, $rows, [3, 2, 1], [true, false, false]],
            [50, $rows, [3, 2, 1], [false, false, false]],
        ];
    }

    public function testEntityWithTransformer()
    {
        $array = $this->allData();

        // Get iterator with entity class and transformer
        $iterator = $this->dbDriver->getIterator(
            'select * from Dogs',
            entityClass: DogEntity::class,
            entityTransformer: new PropertyNameMapper(['id' => 'dogId', 'name' => 'dogName', 'breed' => 'dogBreed', 'weight' => 'dogWeight'])
        );

        // Verify we get objects of the correct type with transformed property names
        $i = 0;
        foreach ($iterator as $singleRow) {
            $entity = $singleRow->entity();
            $this->assertInstanceOf(DogEntity::class, $entity);

            // Verify properties were transformed and populated correctly
            $this->assertEquals($array[$i]['id'], $entity->dogId);
            $this->assertEquals($array[$i]['name'], $entity->dogName);
            $this->assertEquals($array[$i]['breed'], $entity->dogBreed);
            $this->assertEquals($array[$i]['weight'], $entity->dogWeight);

            $i++;
        }

        // Verify we got all the expected rows
        $this->assertEquals(count($array), $i);
    }

    public function testEntityWithComplexTransformer()
    {
        $array = $this->allData();

        $transformer = new PropertyNameMapper(
            [
                'id' => 'animalId',
                'name' => 'animalName',
                'breed' => 'animalType',
                'weight' => 'weightKg'
            ],
            function ($sourceField, $targetField, $value) {
                if ($targetField === 'weightKg') {
                    return $value / 2.20462;
                }
                return $value;
            }
        );

        // Get iterator with entity class and transformer
        $iterator = $this->dbDriver->getIterator(
            'select * from Dogs',
            entityClass: DogEntityComplex::class,
            entityTransformer: $transformer
        );

        // Verify we get objects of the correct type with transformed property names
        $i = 0;
        foreach ($iterator as $singleRow) {
            $entity = $singleRow->entity();
            $this->assertInstanceOf(DogEntityComplex::class, $entity);

            // Verify properties were transformed and populated correctly
            $this->assertEquals($array[$i]['id'], $entity->animalId);
            $this->assertEquals($array[$i]['name'], $entity->animalName);
            $this->assertEquals($array[$i]['breed'], $entity->animalType);
            $this->assertEquals($array[$i]['weight'] / 2.20462, $entity->weightKg);
            $i++;
        }

        // Verify we got all the expected rows
        $this->assertEquals(count($array), $i);
    }
}

