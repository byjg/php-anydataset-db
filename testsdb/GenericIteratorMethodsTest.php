<?php

namespace TestDb;

use ByJG\AnyDataset\Core\Exception\NotFoundException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\Interfaces\DbDriverInterface;
use ByJG\AnyDataset\Db\SqlStatement;
use Override;
use PHPUnit\Framework\TestCase;
use Test\Models\Dogs;

class GenericIteratorMethodsTest extends TestCase
{
    /**
     * @var DbDriverInterface
     */
    protected DbDriverInterface $dbDriver;

    protected DatabaseExecutor $executor;

    #[Override]
    public function setUp(): void
    {
        $this->dbDriver = Factory::getDbInstance('sqlite:///tmp/test_iterator.db');
        $this->executor = DatabaseExecutor::using($this->dbDriver);

        // Create table
        $this->executor->execute(
            'create table Dogs (
            id integer primary key autoincrement,
            breed varchar(50),
            name varchar(50),
            age integer,
            weight numeric(10,2));'
        );

        // Populate data
        $this->executor->execute("INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES ('Mutt', 'Spyke', 8, 8.5);");
        $this->executor->execute("INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES ('Brazilian Terrier', 'Sandy', 3, 3.8);");
        $this->executor->execute("INSERT INTO Dogs (Breed, Name, Age, Weight) VALUES ('Pincher', 'Lola', 1, 1.2);");
    }

    #[Override]
    public function tearDown(): void
    {
        $this->executor->getDriver()->reconnect();
        unlink('/tmp/test_iterator.db');
    }

    // Tests for first() method

    public function testFirstWithResults()
    {
        $iterator = $this->executor->getIterator('select * from Dogs');
        $first = $iterator->first();

        $this->assertIsArray($first);
        $this->assertEquals(1, $first['id']);
        $this->assertEquals('Mutt', $first['breed']);
        $this->assertEquals('Spyke', $first['name']);
    }

    public function testFirstWithNoResults()
    {
        $iterator = $this->executor->getIterator('select * from Dogs where id = 999');
        $first = $iterator->first();

        $this->assertNull($first);
    }

    public function testFirstWithEntityClass()
    {
        $sqlStatement = (new SqlStatement('select * from Dogs'))
            ->withEntityClass(Dogs::class);
        $iterator = $this->executor->getIterator($sqlStatement);
        $first = $iterator->first();

        $this->assertInstanceOf(Dogs::class, $first);
        $this->assertEquals(1, $first->id);
        $this->assertEquals('Mutt', $first->breed);
        $this->assertEquals('Spyke', $first->name);
    }

    public function testFirstWithFiltering()
    {
        $iterator = $this->executor->getIterator('select * from Dogs where age > :age order by age', ['age' => 1]);
        $first = $iterator->first();

        $this->assertIsArray($first);
        $this->assertEquals(2, $first['id']);
        $this->assertEquals('Sandy', $first['name']);
        $this->assertEquals(3, $first['age']);
    }

    // Tests for firstOrFail() method

    public function testFirstOrFailWithResults()
    {
        $iterator = $this->executor->getIterator('select * from Dogs');
        $first = $iterator->firstOrFail();

        $this->assertIsArray($first);
        $this->assertEquals(1, $first['id']);
        $this->assertEquals('Mutt', $first['breed']);
        $this->assertEquals('Spyke', $first['name']);
    }

    public function testFirstOrFailWithNoResults()
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("No results found in iterator");

        $iterator = $this->executor->getIterator('select * from Dogs where id = 999');
        $iterator->firstOrFail();
    }

    public function testFirstOrFailWithEntityClass()
    {
        $sqlStatement = (new SqlStatement('select * from Dogs where id = :id'))
            ->withEntityClass(Dogs::class);
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 2]);
        $first = $iterator->firstOrFail();

        $this->assertInstanceOf(Dogs::class, $first);
        $this->assertEquals(2, $first->id);
        $this->assertEquals('Brazilian Terrier', $first->breed);
        $this->assertEquals('Sandy', $first->name);
    }

    // Tests for exists() method

    public function testExistsWithResults()
    {
        $iterator = $this->executor->getIterator('select * from Dogs');
        $exists = $iterator->exists();

        $this->assertTrue($exists);
    }

    public function testExistsWithNoResults()
    {
        $iterator = $this->executor->getIterator('select * from Dogs where id = 999');
        $exists = $iterator->exists();

        $this->assertFalse($exists);
    }

    public function testExistsWithFiltering()
    {
        $iterator = $this->executor->getIterator('select * from Dogs where breed = :breed', ['breed' => 'Pincher']);
        $exists = $iterator->exists();

        $this->assertTrue($exists);
    }

    public function testExistsWithNonMatchingFilter()
    {
        $iterator = $this->executor->getIterator('select * from Dogs where breed = :breed', ['breed' => 'Poodle']);
        $exists = $iterator->exists();

        $this->assertFalse($exists);
    }

    // Tests for existsOrFail() method

    public function testExistsOrFailWithResults()
    {
        $iterator = $this->executor->getIterator('select * from Dogs');
        $result = $iterator->existsOrFail();

        $this->assertTrue($result);
    }

    public function testExistsOrFailWithNoResults()
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("Iterator is empty");

        $iterator = $this->executor->getIterator('select * from Dogs where id = 999');
        $iterator->existsOrFail();
    }

    public function testExistsOrFailWithFiltering()
    {
        $iterator = $this->executor->getIterator('select * from Dogs where age < :age', ['age' => 5]);
        $result = $iterator->existsOrFail();

        $this->assertTrue($result);
    }

    // Tests for toEntities() method

    public function testToEntitiesWithoutEntityClass()
    {
        $iterator = $this->executor->getIterator('select * from Dogs');
        $entities = $iterator->toEntities();

        $this->assertIsArray($entities);
        $this->assertCount(3, $entities);

        // Without entity class, entities should be arrays
        $this->assertIsArray($entities[0]);
        $this->assertEquals(1, $entities[0]['id']);
        $this->assertEquals('Mutt', $entities[0]['breed']);

        $this->assertIsArray($entities[1]);
        $this->assertEquals(2, $entities[1]['id']);
        $this->assertEquals('Brazilian Terrier', $entities[1]['breed']);

        $this->assertIsArray($entities[2]);
        $this->assertEquals(3, $entities[2]['id']);
        $this->assertEquals('Pincher', $entities[2]['breed']);
    }

    public function testToEntitiesWithEntityClass()
    {
        $sqlStatement = (new SqlStatement('select * from Dogs'))
            ->withEntityClass(Dogs::class);
        $iterator = $this->executor->getIterator($sqlStatement);
        $entities = $iterator->toEntities();

        $this->assertIsArray($entities);
        $this->assertCount(3, $entities);

        // With entity class, entities should be Dogs objects
        $this->assertInstanceOf(Dogs::class, $entities[0]);
        $this->assertEquals(1, $entities[0]->id);
        $this->assertEquals('Mutt', $entities[0]->breed);
        $this->assertEquals('Spyke', $entities[0]->name);

        $this->assertInstanceOf(Dogs::class, $entities[1]);
        $this->assertEquals(2, $entities[1]->id);
        $this->assertEquals('Brazilian Terrier', $entities[1]->breed);
        $this->assertEquals('Sandy', $entities[1]->name);

        $this->assertInstanceOf(Dogs::class, $entities[2]);
        $this->assertEquals(3, $entities[2]->id);
        $this->assertEquals('Pincher', $entities[2]->breed);
        $this->assertEquals('Lola', $entities[2]->name);
    }

    public function testToEntitiesWithFiltering()
    {
        $sqlStatement = (new SqlStatement('select * from Dogs where age > :age order by age'))
            ->withEntityClass(Dogs::class);
        $iterator = $this->executor->getIterator($sqlStatement, ['age' => 1]);
        $entities = $iterator->toEntities();

        $this->assertIsArray($entities);
        $this->assertCount(2, $entities);

        $this->assertInstanceOf(Dogs::class, $entities[0]);
        $this->assertEquals('Sandy', $entities[0]->name);
        $this->assertEquals('Brazilian Terrier', $entities[0]->breed);

        $this->assertInstanceOf(Dogs::class, $entities[1]);
        $this->assertEquals('Spyke', $entities[1]->name);
        $this->assertEquals('Mutt', $entities[1]->breed);
    }

    public function testToEntitiesWithNoResults()
    {
        $sqlStatement = (new SqlStatement('select * from Dogs where id = 999'))
            ->withEntityClass(Dogs::class);
        $iterator = $this->executor->getIterator($sqlStatement);
        $entities = $iterator->toEntities();

        $this->assertIsArray($entities);
        $this->assertCount(0, $entities);
        $this->assertEmpty($entities);
    }

    // Combined tests to ensure methods work together

    public function testCombinedMethodsOnSameIterator()
    {
        // Test that we can check exists before getting first
        $iterator = $this->executor->getIterator('select * from Dogs where age > :age', ['age' => 5]);

        $this->assertTrue($iterator->exists());

        // Getting first should work after checking exists
        $first = $iterator->first();
        $this->assertIsArray($first);
        $this->assertEquals('Spyke', $first['name']);
    }

    public function testMultipleCallsToFirst()
    {
        $iterator = $this->executor->getIterator('select * from Dogs order by id');

        // Multiple calls to first should return the same result
        $first1 = $iterator->first();
        $first2 = $iterator->first();

        $this->assertEquals($first1, $first2);
        $this->assertEquals(1, $first1['id']);
    }

    public function testExistsDoesNotConsumeIterator()
    {
        $iterator = $this->executor->getIterator('select * from Dogs');

        // Check exists
        $this->assertTrue($iterator->exists());

        // Should still be able to iterate through all results
        $count = 0;
        foreach ($iterator as $row) {
            $count++;
        }
        $this->assertEquals(3, $count);
    }
}
