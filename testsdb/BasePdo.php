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
use Exception;
use PHPUnit\Framework\TestCase;

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
        while ($iterator->hasNext()) {
            $singleRow = $iterator->moveNext();
            $this->assertEquals($array[$i++], $singleRow->toArray());
        }

        $this->assertFalse($iterator->isCursorOpen());
    }

    public function testExecuteAndGetId()
    {
        $idInserted = $this->dbDriver->executeAndGetId(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Cat', 'Doris', 7);"
        );

        $this->assertEquals(4, $idInserted);
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

    public function testMultipleRowset()
    {
        if (!$this->dbDriver->isSupportMultiRowset()) {
            $this->markTestSkipped('Skipped: This DbDriver does not support multiple row set');
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

    public function testMultipleQueriesSingleCommand()
    {
        if (!$this->dbDriver->isSupportMultiRowset()) {
            $this->markTestSkipped('Skipped: This DbDriver does not support multiple row set');
        }

        // Sanity check: Assert that the data was not inserted before
        $inserted = $this->dbDriver->getIterator('select * from Dogs where Id = :id', ['id' => 4])->toArray();
        $this->assertEquals([], $inserted);

        // Sanity check: Assert that the data was not updated before
        $updated = $this->dbDriver->getIterator('select * from Dogs where Id = :id', ['id' => 2])->toArray()[0];
        $this->assertEquals(
            [
                "id" => 2,
                "breed" => "Brazilian Terrier",
                "name" => "Sandy",
                "age" => 3,
                "weight" => 3.8,
            ],
            $updated
        );

        // Execute multiple different statements (INSERT + UPDATE) in a single command
        $sql = "INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES ('Bird', 'Blue', 7, 4.2); " .
            "UPDATE Dogs SET Age = 11, Weight = 5.0 WHERE Id = 2;";

        $this->dbDriver->execute($sql);

        // Assert that the data was inserted correctly
        $inserted = $this->dbDriver->getIterator('select * from Dogs where Id = :id', ['id' => 4])->toArray()[0];
        $this->assertEquals(
            [
                "id" => 4,
                "breed" => "Bird",
                "name" => "Blue",
                "age" => 7,
                "weight" => 4.2,
            ],
            $inserted
        );

        // Assert that the data was updated correctly
        $updated = $this->dbDriver->getIterator('select * from Dogs where Id = :id', ['id' => 2])->toArray()[0];
        $this->assertEquals(
            [
                "id" => 2,
                "breed" => "Brazilian Terrier",
                "name" => "Sandy",
                "age" => 11,
                "weight" => 5.0,
            ],
            $updated
        );
    }

    public function testMultipleQueriesSingleCommandAndParams()
    {
        if (!$this->dbDriver->isSupportMultiRowset()) {
            $this->markTestSkipped('Skipped: This DbDriver does not support multiple row set');
        }

        // Sanity check: Assert that the data was not inserted before
        $inserted = $this->dbDriver->getIterator('select * from Dogs where Id = :id', ['id' => 4])->toArray();
        $this->assertEquals([], $inserted);

        // Sanity check: Assert that the data was not updated before
        $updated = $this->dbDriver->getIterator('select * from Dogs where Id = :id', ['id' => 2])->toArray()[0];
        $this->assertEquals(
            [
                "id" => 2,
                "breed" => "Brazilian Terrier",
                "name" => "Sandy",
                "age" => 3,
                "weight" => 3.8,
            ],
            $updated
        );

        // Execute multiple different statements (INSERT + UPDATE) in a single command
        $sql = "INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES (:breed1, :name1, :age1, :weight1); " .
            "UPDATE Dogs SET Age = :age2, Weight = :weight2 WHERE Id = :id2;";

        $this->dbDriver->execute($sql, [
            "breed1" => "Bird",
            "name1" => "Blue",
            "age1" => 7,
            "weight1" => 4.2,
            "age2" => 13,
            "weight2" => 5.0,
            "id2" => 2,
        ]);

        $inserted = $this->dbDriver->getIterator('select * from Dogs where Id = :id', ['id' => 4])->toArray()[0];
        $this->assertEquals(
            [
                "id" => 4,
                "breed" => "Bird",
                "name" => "Blue",
                "age" => 7,
                "weight" => 4.2,
            ],
            $inserted
        );

        $updated = $this->dbDriver->getIterator('select * from Dogs where Id = :id', ['id' => 2])->toArray()[0];
        $this->assertEquals(
            [
                "id" => 2,
                "breed" => "Brazilian Terrier",
                "name" => "Sandy",
                "age" => 13,
                "weight" => 5.0,
            ],
            $updated
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
        $iterator = $this->dbDriver->getIterator('select * from Dogs where id = :id', ['id' => 1], $cacheEngine, 60);
        $this->assertEquals(
            [
                [ 'id'=> 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "weight" => 8.5, "__id" => 0, "__key" => 0],
            ],
            $iterator->toArray()
        );

        // Remove it from DB (Still in cache) - Execute don't use cache
        $this->dbDriver->execute("delete from Dogs where id = :id", ['id' => 1]);

        // Try get from cache
        $iterator = $this->dbDriver->getIterator('select * from Dogs where id = :id', ['id' => 1], $cacheEngine, 60);
        $this->assertEquals(
            [
                [ 'id'=> 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "weight" => 8.5, "__id" => 0, "__key" => 0],
            ],
            $iterator->toArray()
        );
    }

    public function testCachedResultsNotFound()
    {
        $cacheEngine = new ArrayCacheEngine();

        // Get the first from Db and then cache it;
        $iterator = $this->dbDriver->getIterator('select * from Dogs where id = :id', ['id' => 4], $cacheEngine, 60);
        $this->assertEquals(
            [],
            $iterator->toArray()
        );
        $iterator = $this->dbDriver->getIterator('select * from Dogs where id = :id', ['id' => 1], $cacheEngine, 60);
        $this->assertEquals(
            [
                [ 'id'=> 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "weight" => 8.5, "__id" => 0, "__key" => 0],
            ],
            $iterator->toArray()
        );

        // Update Record
        $this->dbDriver->execute("INSERT INTO Dogs (Breed, Name, Age) VALUES (:breed, :name, :age);", ["breed" => "Cat", "name" => "Doris", "age" => 6]);

        // Try get from cache (should have the same result from before)
        $iterator = $this->dbDriver->getIterator('select * from Dogs where id = :id', ['id' => 1], $cacheEngine, 60);
        $this->assertEquals(
            [
                [ 'id'=> 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "weight" => 8.5, "__id" => 0, "__key" => 0],
            ],
            $iterator->toArray()
        );
        $iterator = $this->dbDriver->getIterator('select * from Dogs where id = :id', ['id' => 4], $cacheEngine, 60);
        $this->assertEquals(
            [],
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
     * @dataProvider dataProviderPreFetch
     * @return void
     * @psalm-suppress UndefinedMethod
     */
    public function testPreFetchWhile(int $preFetch, array $rows, array $expected, array $expectedCursor)
    {
        $iterator = $this->dbDriver->getIterator('select * from Dogs', preFetch: $preFetch);

        $i = 0;
        while ($iterator->hasNext()) {
            $row = $iterator->moveNext();
            $this->assertEquals($rows[$i], $row->toArray(), "Row $i");
            $this->assertEquals($i, $iterator->key(), "Key Row $i");
            $this->assertEquals($expected[$i], $iterator->getPreFetchBufferSize(), "PreFetchBufferSize Row " . $iterator->key());
            $this->assertEquals($expectedCursor[$i], $iterator->isCursorOpen(), "CursorOpen Row " . $iterator->key());
            $i++;
        }
    }

    /**
     * @dataProvider dataProviderPreFetch
     * @psalm-suppress UndefinedMethod
     * @return void
     */
    public function testPreFetchForEach(int $preFetch, array $rows, array $expected, array $expectedCursor)
    {
        $iterator = $this->dbDriver->getIterator('select * from Dogs', preFetch: $preFetch);

        $i = 0;
        foreach ($iterator as $row) {
            $this->assertEquals($rows[$i], $row->toArray(), "Row $i");
            $this->assertEquals($i, $iterator->key(), "Key Row $i");
            $this->assertEquals($expected[$i], $iterator->getPreFetchBufferSize(), "PreFetchBufferSize Row $i");
            $this->assertEquals($expectedCursor[$i], $iterator->isCursorOpen(), "CursorOpen Row $i");
            $i++;
        }
    }

    protected function dataProviderPreFetch()
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
            [0, $rows, [1, 1, 0], [true, true, false]],
            [1, $rows, [1, 1, 0], [true, true, false]],
            [2, $rows, [2, 1, 0], [true, false, false]],
            [3, $rows, [2, 1, 0], [false, false, false]],
            [50, $rows, [2, 1, 0], [false, false, false]],
        ];
    }
}

