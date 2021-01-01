<?php

namespace TestsDb\AnyDataset;

use ByJG\AnyDataset\Core\Exception\NotImplementedException;
use ByJG\AnyDataset\Db\DbCached;
use ByJG\AnyDataset\Db\DbDriverInterface;
use PHPUnit\Framework\TestCase;

abstract class BasePdo extends TestCase
{

    /**
     * @var DbDriverInterface
     */
    protected $dbDriver;

    /**
     * @throws \Exception
     */
    public function setUp()
    {
        $this->createInstance();
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
        foreach ($array as $param) {
            $this->dbDriver->execute(
                "INSERT INTO Dogs (Breed, Name, Age) VALUES (:breed, :name, :age);",
                $param
            );
        }

        if ($this->dbDriver->getUri()->getQueryPart("stmtcache") == "true") {
            // One cache for CREATE TABLE... and another for INSERT INTO...
            $this->assertEquals(2, $this->dbDriver->getCountStmtCache());
        } else {
            $this->assertEquals(0, $this->dbDriver->getCountStmtCache());
        }
    }

    abstract protected function createDatabase();

    abstract protected function deleteDatabase();

    public function tearDown()
    {
        $this->deleteDatabase();
    }

    protected function allData()
    {
        return [
            [
                'breed' => 'Mutt',
                'name' => 'Spyke',
                'age' => 8,
                'id' => 1
            ],
            [
                'breed' => 'Brazilian Terrier',
                'name' => 'Sandy',
                'age' => 3,
                'id' => 2
            ],
            [
                'breed' => 'Pinscher',
                'name' => 'Lola',
                'age' => 1,
                'id' => 3
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
                'age'
            ],
            $allFields
        );
    }

    public function testMultipleRowset()
    {
        if (!$this->dbDriver->isSupportMultRowset()) {
            $this->markTestSkipped('Skipped: This DbDriver does not support multiple row set');
            return;
        }

        $sql = "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Cat', 'Doris', 7); " .
            "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Dog', 'Lolla', 1); ";

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
            "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Dog', '€ Sign Pètit Pannô', 6);"
        );

        $iterator = $this->dbDriver->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();

        $this->assertEquals(4, $row[0]["id"]);
        $this->assertEquals('Dog', $row[0]["breed"]);
        $this->assertEquals('€ Sign Pètit Pannô', $row[0]["name"]);
        $this->assertEquals(6, $row[0]["age"]);
    }

    public function testEscapeQuote()
    {
        $this->dbDriver->execute(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Dog', 'Puppy\'s Master', 6);"
        );

        $iterator = $this->dbDriver->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();

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

        $this->assertEquals(4, $row[0]["id"]);
        $this->assertEquals('Dog', $row[0]["breed"]);
        $this->assertEquals('Puppy\'s Master', $row[0]["name"]);
        $this->assertEquals(6, $row[0]["age"]);
    }

    public function testEscapeQuoteWithMixedParam()
    {
        $this->dbDriver->execute(
            "INSERT INTO Dogs (Breed, Name, Age) VALUES (:breed, 'Puppy\'s Master', :age);",
            [
                "breed" => 'Dog',
                "age" => 6
            ]
        );

        $iterator = $this->dbDriver->getIterator('select Id, Breed, Name, Age from Dogs where id = 4');
        $row = $iterator->toArray();

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

        $this->assertEquals(4, $row[0]["id"]);
        $this->assertEquals('Dog', $row[0]["breed"]);
        $this->assertEquals('FÃ©lix', $row[0]["name"]);
        $this->assertEquals(6, $row[0]["age"]);
    }

    public function testCachedResults()
    {
        $dbCached = new DbCached($this->dbDriver, \ByJG\Cache\Factory::createArrayPool(), 600);

        // Get the first from Db and then cache it;
        $iterator = $dbCached->getIterator('select * from Dogs where id = :id', ['id' => 1]);
        $this->assertEquals(
            [
                [ 'id'=> 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "__id" => 0, "__key" => 0],
            ],
            $iterator->toArray()
        );

        // Remove it from DB (Still in cache) - Execute don't use cache
        $dbCached->execute("delete from Dogs where id = :id", ['id' => 1]);

        // Try get from cache
        $iterator = $dbCached->getIterator('select * from Dogs where id = :id', ['id' => 1]);
        $this->assertEquals(
            [
                [ 'id'=> 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "__id" => 0, "__key" => 0],
            ],
            $iterator->toArray()
        );
    }

    public function testCachedResultsNotFound()
    {
        $dbCached = new DbCached($this->dbDriver, \ByJG\Cache\Factory::createArrayPool(), 600);

        // Get the first from Db and then cache it;
        $iterator = $dbCached->getIterator('select * from Dogs where id = :id', ['id' => 4]);
        $this->assertEquals(
            [],
            $iterator->toArray()
        );
        $iterator = $dbCached->getIterator('select * from Dogs where id = :id', ['id' => 1]);
        $this->assertEquals(
            [
                [ 'id'=> 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "__id" => 0, "__key" => 0],
            ],
            $iterator->toArray()
        );

        // Remove it from DB (Still in cache)
        $this->dbDriver->execute("INSERT INTO Dogs (Breed, Name, Age) VALUES (:breed, :name, :age);", ["breed" => "Cat", "name" => "Doris", "age" => 6]);

        // Try get from cache
        $iterator = $dbCached->getIterator('select * from Dogs where id = :id', ['id' => 1]);
        $this->assertEquals(
            [
                [ 'id'=> 1, 'breed' => "Mutt", 'name' => 'Spyke', "age" => 8, "__id" => 0, "__key" => 0],
            ],
            $iterator->toArray()
        );
        $iterator = $dbCached->getIterator('select * from Dogs where id = :id', ['id' => 4]);
        $this->assertEquals(
            [],
            $iterator->toArray()
        );
    }

}

