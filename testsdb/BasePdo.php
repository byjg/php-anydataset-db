<?php

namespace TestDb;

use ByJG\AnyDataset\Core\Exception\NotImplementedException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
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
use PDOException;
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
    protected DbDriverInterface $dbDriver;

    protected DatabaseExecutor $executor;

    protected string $escapeQuote = "''";

    protected int $floatSize = 10;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        $this->dbDriver = $this->createInstance();
        $this->executor = DatabaseExecutor::using($this->dbDriver);
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
            $this->executor->execute(
                $sqlStatement,
                $param
            );
        }
    }

    abstract protected function createDatabase();

    abstract protected function deleteDatabase();

    public function tearDown(): void
    {
        $this->executor->getDriver()->reconnect();
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
        $iterator = $this->executor->getIterator('select * from Dogs');
        $this->assertEquals($array, $iterator->toArray());

        // Step 2
        $iterator = $this->executor->getIterator('select * from Dogs');
        $i = 0;
        foreach ($iterator as $singleRow) {
            $this->assertEquals($array[$i++], $singleRow->toArray());
        }

        // Step 3
        $iterator = $this->executor->getIterator('select * from Dogs');
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
        $iterator = $this->executor->getIterator($sqlStatement);
        $this->assertEquals($array, $iterator->toArray());

        // Step 2
        $sqlStatement = new SqlStatement('select * from Dogs where id = :id', ['id' => 1]);
        $iterator = $this->executor->getIterator($sqlStatement);
        $this->assertEquals([$array[0]], $iterator->toArray());

        // Step 2
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 2]);
        $this->assertEquals([$array[1]], $iterator->toArray());
    }

    public function testGetIteratorSqlStatementAndPartialParams()
    {
        $array = $this->allData();

        $sqlStatement = new SqlStatement('select * from Dogs where age <= :age and name = :name', ['age' => 5]);

        $iterator = $this->executor->getIterator($sqlStatement, ['name' => 'Spyke']);
        $this->assertCount(0, $iterator->toArray());

        $iterator = $this->executor->getIterator($sqlStatement, ['name' => 'Sandy']);
        $this->assertCount(1, $iterator->toArray());
    }


    public function testGetIteratorWithEntityClass()
    {
        $array = $this->allData();

        // Get iterator with entity class
        $sqlStatement = (new SqlStatement('select * from Dogs'))->withEntityClass(Dogs::class);
        $iterator = $this->executor->getIterator($sqlStatement);

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

        $sqlStatement = (new SqlStatement('select * from Dogs'))->withEntityClass(Dogs::class);

        // Get iterator with entity class
        $iterator = $this->executor->getIterator($sqlStatement);

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
        $this->testExecuteAndGetId(false);
    }

    public function testExecuteAndGetId(bool $getId = false)
    {
        $check = $this->executor->getIterator("select * from Dogs where Id = 4");
        $this->assertEmpty($check->toArray());

        $params = [
            "breed" => 'Cat',
            "name" => 'Doris',
            "age" => 7
        ];

        if ($getId) {
            $idInserted = $this->executor->executeAndGetId(
                "INSERT INTO Dogs (Breed, Name, Age) VALUES (:breed, :name, :age);",
                $params
            );
            $this->assertEquals(4, $idInserted);
        } else {
            $this->executor->execute(
                "INSERT INTO Dogs (Breed, Name, Age) VALUES (:breed, :name, :age);",
                $params
            );
        }

        $check = $this->executor->getIterator("select * from Dogs where Id = 4")->toArray();
        $this->assertEquals(4, $check[0]["id"]);
        $this->assertEquals('Cat', $check[0]["breed"]);
        $this->assertEquals('Doris', $check[0]["name"]);
        $this->assertEquals(7, $check[0]["age"]);
        $this->assertEquals(null, $check[0]["weight"]);
    }

    public function testGetAllFields()
    {
        $allFields = $this->executor->getAllFields('Dogs');

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
            $this->executor->getScalar('select count(*) from Dogs where Id = :id', ['id' => 2])
        );

        $this->assertEquals(
            2,
            $this->executor->getScalar('select Id from Dogs where Id = :id', ['id' => 2])
        );

        $this->assertEquals(
            3,
            $this->executor->getScalar('select count(*) from Dogs')
        );

        $this->assertFalse(
            $this->executor->getScalar('select Id from Dogs where Id = :id', ['id' => 9999])
        );
    }

    public function testGetScalarWithSqlStatement()
    {
        $sqlStatement = new SqlStatement('select Id from Dogs where Id = :id', ['id' => 2]);
        $this->assertEquals(
            2,
            $this->executor->getScalar($sqlStatement)
        );

        $this->assertEquals(
            1,
            $this->executor->getScalar($sqlStatement, ['id' => 1])
        );

        $this->assertFalse(
            $this->executor->getScalar($sqlStatement, ['id' => 9999])
        );
    }

    public function testMultipleRowsetGetId()
    {
        $this->testMultipleRowset(true);
    }

    public function testMultipleRowset(bool $getId = false)
    {
        if (!$this->executor->getDriver()->isSupportMultiRowset()) {
            $this->markTestSkipped('This database driver does not support multiple result sets');
        }

        $check = $this->executor->getIterator("select * from Dogs where Id > 3");
        $this->assertEmpty($check->toArray());

        $sql = "INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES ('Cat', 'Doris', 7, 4.2); " .
            "INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES ('Dog', 'Lolla', 1, 1.4); ";

        if ($getId) {
            $idInserted = $this->executor->executeAndGetId($sql);
            $this->assertSame(5, intval($idInserted));
        } else {
            $this->executor->execute($sql);
        }

        $item1 = $this->executor->getIterator('select Id, Breed, Name, Age, Weight from Dogs where Id = 4')->toArray();
        $this->assertEquals(4, $item1[0]["id"]);
        $this->assertEquals('Cat', $item1[0]["breed"]);
        $this->assertEquals('Doris', $item1[0]["name"]);
        $this->assertEquals(7, $item1[0]["age"]);
        $this->assertEquals(4.2, $item1[0]["weight"]);

        $item2 = $this->executor->getIterator('select Id, Breed, Name, Age, Weight from Dogs where Id = 5')->toArray();
        $this->assertEquals(5, $item2[0]["id"]);
        $this->assertEquals('Dog', $item2[0]["breed"]);
        $this->assertEquals('Lolla', $item2[0]["name"]);
        $this->assertEquals(1, $item2[0]["age"]);
        $this->assertEquals(1.4, $item2[0]["weight"]);

    }

    public function testMultipleRowsetError1GetId()
    {
        $this->testMultipleRowsetError1(true);
    }

    public function testMultipleRowsetError1(bool $getId = false)
    {
        if (!$this->executor->getDriver()->isSupportMultiRowset()) {
            $this->markTestSkipped('This database driver does not support multiple result sets');
        }

        $check = $this->executor->getIterator("select * from Dogs where Id > 3");
        $this->assertEmpty($check->toArray());

        $this->expectException(PDOException::class);

        $sql = "INSERT INTO NonExistent (Breed, Name, Age, Weight) VALUES ('Cat', 'Doris', 7, 4.2); " .
            "INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES ('Dog', 'Lolla', 1, 1.4); ";

        try {
            if ($getId) {
                $this->executor->executeAndGetId($sql);
            } else {
                $this->executor->execute($sql);
            }
        } catch (PDOException $e) {
            $check = $this->executor->getIterator("select * from Dogs where Id > 3");
            $this->assertEmpty($check->toArray());
            throw $e;
        }

    }

    public function testMultipleRowsetError2GetId()
    {
        $this->testMultipleRowsetError2(true);
    }

    public function testMultipleRowsetError2(bool $getId = false)
    {
        if (!$this->executor->getDriver()->isSupportMultiRowset()) {
            $this->markTestSkipped('This database driver does not support multiple result sets');
        }

        $check = $this->executor->getIterator("select * from Dogs where Id > 3");
        $this->assertEmpty($check->toArray());

        $this->expectException(PDOException::class);

        $sql = "INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES ('Cat', 'Doris', 7, 4.2); " .
            "INSERT INTO NonExistent (Breed, Name, Age, Weight) VALUES ('Dog', 'Lolla', 1, 1.4); ";

        $this->executor->beginTransaction();
        try {
            if ($getId) {
                $this->executor->executeAndGetId($sql);
            } else {
                $this->executor->execute($sql);
            }
        } catch (PDOException $e) {
            $this->executor->rollbackTransaction();
            $check = $this->executor->getIterator("select * from Dogs where Id > 3");
            $this->assertEmpty($check->toArray());
            throw $e;
        }
    }

    public function testMultipleRowsetError3GetId()
    {
        $this->testMultipleRowsetError3(true);
    }

    public function testMultipleRowsetError3(bool $getId = false)
    {
        $this->expectException(PDOException::class);

        if (!$this->executor->getDriver()->isSupportMultiRowset()) {
            $this->markTestSkipped('This database driver does not support multiple result sets');
        }

        $sql = "INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES ('Cat', 'Doris', 7, 4.2); " .
            "INSERT INTO NonExistent (Breed, Name, Age, Weight) VALUES ('Dog', 'Lolla', 1, 1.4); " .
            "INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES ('Cat', 'Doris', 7, 4.2);";

        if ($getId) {
            $this->executor->executeAndGetId($sql);
        } else {
            $this->executor->execute($sql);
        }
    }

    public function testMultipleQueriesSingleCommand()
    {
        if (!$this->executor->getDriver()->isSupportMultiRowset()) {
            $this->markTestSkipped('Skipped: This DbDriver does not support multiple row set');
        }

        // Sanity check: Assert that the data was not inserted before
        $inserted = $this->executor->getIterator('select * from Dogs where Id = :id', ['id' => 4])->toArray();
        $this->assertEquals([], $inserted);

        // Sanity check: Assert that the data was not updated before
        $updated = $this->executor->getIterator('select * from Dogs where Id = :id', ['id' => 2])->toArray()[0];
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

        $this->executor->execute($sql);

        // Assert that the data was inserted correctly
        $inserted = $this->executor->getIterator('select * from Dogs where Id = :id', ['id' => 4])->toArray()[0];
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
        $updated = $this->executor->getIterator('select * from Dogs where Id = :id', ['id' => 2])->toArray()[0];
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
        if (!$this->executor->getDriver()->isSupportMultiRowset()) {
            $this->markTestSkipped('Skipped: This DbDriver does not support multiple row set');
        }

        // Sanity check: Assert that the data was not inserted before
        $inserted = $this->executor->getIterator('select * from Dogs where Id = :id', ['id' => 4])->toArray();
        $this->assertEquals([], $inserted);

        // Sanity check: Assert that the data was not updated before
        $updated = $this->executor->getIterator('select * from Dogs where Id = :id', ['id' => 2])->toArray()[0];
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

        $this->executor->execute($sql, [
            "breed1" => "Bird",
            "name1" => "Blue",
            "age1" => 7,
            "weight1" => 4.2,
            "age2" => 13,
            "weight2" => 5.0,
            "id2" => 2,
        ]);

        $inserted = $this->executor->getIterator('select * from Dogs where Id = :id', ['id' => 4])->toArray()[0];
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

        $updated = $this->executor->getIterator('select * from Dogs where Id = :id', ['id' => 2])->toArray()[0];
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
        $id = $this->executor->executeAndGetId($sql);
        $this->assertEquals(4, $id);

        $sql = "select id from Dogs where name = 'a:Doris'";
        $id = $this->executor->getScalar($sql);
        $this->assertEquals(4, $id);
    }

    public function testInsertSpecialChars()
    {
        $this->executor->execute(
            "INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES ('Dog', '€ Sign Pètit Pannô', 6, 3.2);"
        );

        $iterator = $this->executor->getIterator('select Id, Breed, Name, Age, Weight from Dogs where id = 4');
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

        $this->executor->execute(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Dog', 'Puppy{$escapeQuote}s Master', 6);"
        );

        $iterator = $this->executor->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();
        $this->assertFalse($iterator->isCursorOpen());

        $this->assertEquals(4, $row[0]["id"]);
        $this->assertEquals('Dog', $row[0]["breed"]);
        $this->assertEquals('Puppy\'s Master', $row[0]["name"]);
        $this->assertEquals(6, $row[0]["age"]);
    }

    public function testEscapeQuoteWithParam()
    {
        $this->executor->execute(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES (:breed, :name, :age);",
            [
                "breed" => 'Dog',
                "name" => "Puppy's Master",
                "age" => 6
            ]
        );

        $iterator = $this->executor->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
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

        $this->executor->execute(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES (:breed, 'Puppy{$escapeQuote}s Master', :age);",
            [
                "breed" => 'Dog',
                "age" => 6
            ]
        );

        $iterator = $this->executor->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();
        $this->assertFalse($iterator->isCursorOpen());

        $this->assertEquals(4, $row[0]["id"]);
        $this->assertEquals('Dog', $row[0]["breed"]);
        $this->assertEquals('Puppy\'s Master', $row[0]["name"]);
        $this->assertEquals(6, $row[0]["age"]);
    }

    public function testGetBuggyUT8()
    {
        $this->executor->execute(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Dog', 'FÃ©lix', 6);"
        );

        $iterator = $this->executor->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();
        $this->assertFalse($iterator->isCursorOpen());

        $this->assertEquals(4, $row[0]["id"]);
        $this->assertEquals('Dog', $row[0]["breed"]);
        $this->assertEquals('FÃ©lix', $row[0]["name"]);
        $this->assertEquals(6, $row[0]["age"]);
    }

    public function testDontParseParam()
    {
        $newUri = $this->executor->getDriver()->getUri()->withQueryKeyValue(DbPdoDriver::DONT_PARSE_PARAM, "");
        $newConn = Factory::getDbInstance($newUri);
        $it = $newConn->getIterator('select Id, Breed, Name, Age from Dogs where id = :field', ["field" => 1]);
        $this->assertCount(1, $it->toArray());
        $this->assertFalse($it->isCursorOpen());
    }

    public function testDontParseParam_2()
    {
        $it = $this->executor->getIterator('select Id, Breed, Name, Age from Dogs where id = :field');
        $this->assertCount(0, $it->toArray());
    }

    public function testDontParseParam_3()
    {
        $newUri = $this->executor->getDriver()->getUri()->withQueryKeyValue(DbPdoDriver::DONT_PARSE_PARAM, "");
        $newConn = Factory::getDbInstance($newUri);
        $it = $newConn->getIterator('select Id, Breed, Name, Age from Dogs where id = :field');
        $this->assertCount(0, $it->toArray());
    }


    public function testCachedResults()
    {
        // Check with no cache at all
        $iterator = $this->executor->getIterator('select * from Dogs where id = :id', ['id' => 1]);
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
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 1]);
        $this->assertEquals(
            [
                ['id' => 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "weight" => 8.5],
            ],
            $iterator->toArray()
        );

        // Remove it from DB (Still in cache) - Execute don't use cache
        $this->executor->execute("delete from Dogs where id = :id", ['id' => 1]);

        // Try get from cache
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 1]);
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
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEquals(
            [],
            $iterator->toArray()
        );

        // Get the second from Db and then cache it, since is the same statement
        // However, the cache key is different because of the different param;
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 1]);
        $this->assertEquals(
            [
                ['id' => 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "weight" => 8.5],
            ],
            $iterator->toArray()
        );


        // Update Record
        $id = $this->executor->executeAndGetId("INSERT INTO Dogs (Breed, Name, Age) VALUES (:breed, :name, :age);", ["breed" => "Cat", "name" => "Doris", "age" => 6]);
        $this->assertEquals(4, $id);
        $this->executor->execute("update Dogs set age = 15 where id = 1");

        // Try get from cache (should have the same result from before)
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 1]);
        $this->assertEquals(
            [
                ['id' => 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "weight" => 8.5],
            ],
            $iterator->toArray()
        );

        // Try get from cache (should have the same result from before)
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEquals(
            [],
            $iterator->toArray()
        );

        // Create a new Statement with no cache
        $sqlStatement = new SqlStatement('select * from Dogs where id = :id');
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEquals(
            [
                ['id' => 4, 'breed' => "Cat", 'name' => 'Doris', "age" => 6, "weight" => null],
            ],
            $iterator->toArray()
        );
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 1]);
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
        $metadata = $this->executor->getHelper()->getTableMetadata($this->executor, 'Dogs');

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
        $iterator = $this->executor->getIterator('select Id, Breed, Name, Age from Dogs where id = 1');
        $row = $iterator->toArray();
        $this->assertEquals(1, $row[0]["id"]);

        $this->executor->getDriver()->disconnect();

        $this->expectException(DbDriverNotConnected::class);
        $iterator = $this->executor->getIterator('select Id, Breed, Name, Age from Dogs where id = 1');
    }

    public function testReconnect()
    {
        $this->assertFalse($this->executor->getDriver()->reconnect());
        $this->assertTrue($this->executor->getDriver()->reconnect(true));
        $iterator = $this->executor->getIterator('select Id, Breed, Name, Age from Dogs where id = 1');
    }

    public function testCommitTransaction()
    {
        $this->assertFalse($this->executor->hasActiveTransaction());
        $this->assertNull($this->executor->activeIsolationLevel());
        $this->executor->beginTransaction(IsolationLevelEnum::SERIALIZABLE);
        $this->assertTrue($this->executor->hasActiveTransaction());
        $this->assertEquals(IsolationLevelEnum::SERIALIZABLE, $this->executor->activeIsolationLevel());

        $idInserted = $this->executor->executeAndGetId(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Cat', 'Doris', 7);"
        );
        $this->executor->commitTransaction();
        $this->assertFalse($this->executor->hasActiveTransaction());
        $this->assertNull($this->executor->activeIsolationLevel());

        $this->assertEquals(4, $idInserted);

        $iterator = $this->executor->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();

        $this->assertEquals(4, $row[0]["id"]);
        $this->assertEquals('Cat', $row[0]["breed"]);
        $this->assertEquals('Doris', $row[0]["name"]);
        $this->assertEquals(7, $row[0]["age"]);
    }

    public function testRollbackTransaction()
    {
        $this->assertFalse($this->executor->hasActiveTransaction());
        $this->assertNull($this->executor->activeIsolationLevel());
        $this->executor->beginTransaction(IsolationLevelEnum::REPEATABLE_READ);
        $this->assertTrue($this->executor->hasActiveTransaction());
        $this->assertEquals(IsolationLevelEnum::REPEATABLE_READ, $this->executor->activeIsolationLevel());

        $idInserted = $this->executor->executeAndGetId(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Cat', 'Doris', 7);"
        );
        $this->executor->rollbackTransaction();
        $this->assertFalse($this->executor->hasActiveTransaction());
        $this->assertNull($this->executor->activeIsolationLevel());

        $this->assertEquals(4, $idInserted);

        $iterator = $this->executor->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();

        $this->assertEmpty($row);
    }

    public function testCommitWithoutTransaction()
    {
        $this->expectException(TransactionNotStartedException::class);
        $this->executor->commitTransaction();
    }

    public function testRollbackWithoutTransaction()
    {
        $this->expectException(TransactionNotStartedException::class);
        $this->executor->rollbackTransaction();
    }

    public function testRequiresTransaction()
    {
        $this->assertFalse($this->executor->hasActiveTransaction());
        $this->assertNull($this->executor->activeIsolationLevel());
        $this->assertEquals(0, $this->executor->remainingCommits());

        $this->executor->beginTransaction(IsolationLevelEnum::READ_COMMITTED);
        $this->assertTrue($this->executor->hasActiveTransaction());
        $this->assertEquals(IsolationLevelEnum::READ_COMMITTED, $this->executor->activeIsolationLevel());
        $this->assertEquals(1, $this->executor->remainingCommits());

        $this->executor->requiresTransaction();
        $this->assertTrue($this->executor->hasActiveTransaction());
        $this->assertEquals(IsolationLevelEnum::READ_COMMITTED, $this->executor->activeIsolationLevel());
        $this->assertEquals(1, $this->executor->remainingCommits());

        $this->executor->commitTransaction();
        $this->assertFalse($this->executor->hasActiveTransaction());
        $this->assertNull($this->executor->activeIsolationLevel());
        $this->assertEquals(0, $this->executor->remainingCommits());
    }

    public function testRequiresTransactionWithoutTransaction()
    {
        $this->expectException(TransactionNotStartedException::class);
        $this->executor->requiresTransaction();
    }

    public function testBeginTransactionTwice()
    {
        $this->executor->beginTransaction(IsolationLevelEnum::READ_UNCOMMITTED);
        $this->expectException(TransactionStartedException::class);
        try {
            $this->executor->beginTransaction();
        } finally {
            $this->executor->rollbackTransaction();
            $this->assertFalse($this->executor->hasActiveTransaction());
            $this->assertNull($this->executor->activeIsolationLevel());
            $this->assertEquals(0, $this->executor->remainingCommits());
        }
    }

    public function testJoinTransaction()
    {
        $this->executor->beginTransaction(IsolationLevelEnum::READ_UNCOMMITTED);
        $this->assertEquals(1, $this->executor->remainingCommits());
        $this->assertEquals(IsolationLevelEnum::READ_UNCOMMITTED, $this->executor->activeIsolationLevel());

        $this->executor->beginTransaction(IsolationLevelEnum::READ_UNCOMMITTED, true);
        $this->assertEquals(2, $this->executor->remainingCommits());
        $this->assertEquals(IsolationLevelEnum::READ_UNCOMMITTED, $this->executor->activeIsolationLevel());

        $this->executor->commitTransaction();
        $this->assertEquals(1, $this->executor->remainingCommits());
        $this->assertTrue($this->executor->hasActiveTransaction());
        $this->assertEquals(IsolationLevelEnum::READ_UNCOMMITTED, $this->executor->activeIsolationLevel());

        $this->executor->commitTransaction();
        $this->assertEquals(0, $this->executor->remainingCommits());
        $this->assertFalse($this->executor->hasActiveTransaction());
        $this->assertNull($this->executor->activeIsolationLevel());
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
        $iterator = $this->executor->getIterator('select * from Dogs', preFetch: $preFetch);

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
        $iterator = $this->executor->getIterator('select * from Dogs', preFetch: $preFetch);

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
        $iterator = $this->executor->getIterator('select * from Dogs', preFetch: $preFetch);

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
        $sqlStatement = (new SqlStatement('select * from Dogs'))
            ->withEntityClass(DogEntity::class)
            ->withEntityTransformer(new PropertyNameMapper(['id' => 'dogId', 'name' => 'dogName', 'breed' => 'dogBreed', 'weight' => 'dogWeight']));
        $iterator = $this->executor->getIterator($sqlStatement);

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
        $sqlStatement = (new SqlStatement('select * from Dogs'))
            ->withEntityClass(DogEntityComplex::class)
            ->withEntityTransformer($transformer);
        $iterator = $this->executor->getIterator($sqlStatement);

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

