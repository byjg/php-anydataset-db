<?php

namespace Test;

use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\Helpers\DbSqliteFunctions;
use ByJG\AnyDataset\Db\SqlStatement;
use ByJG\Cache\Psr16\ArrayCacheEngine;
use ByJG\Serializer\PropertyHandler\PropertyNameMapper;
use ByJG\Util\Uri;
use Override;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\Models\Info;
use Test\Models\InfoEntity;
use Test\Models\UserEntity;

class PdoSqliteTest extends TestCase
{
    /**
     * @var DbDriverInterface
     */
    protected $dbDriver;

    #[Override]
    public function setUp(): void
    {
        $this->dbDriver = Factory::getDbInstance('sqlite:///tmp/test.db');

        $this->dbDriver->execute(
            'create table users (
            id integer primary key  autoincrement, 
            name varchar(45), 
            createdate datetime);'
        );
        $this->dbDriver->execute("insert into users (name, createdate) values ('John Doe', '2017-01-02')");
        $this->dbDriver->execute("insert into users (name, createdate) values ('Jane Doe', '2017-01-04')");
        $this->dbDriver->execute("insert into users (name, createdate) values ('JG', '1974-01-26')");


        $this->dbDriver->execute(
            'create table info (
            id integer primary key  autoincrement,
            iduser INTEGER,
            number numeric(10,2),
            property varchar(45));'
        );
        $this->dbDriver->execute("insert into info (iduser, number, property) values (1, 10.45, 'xxx')");
        $this->dbDriver->execute("insert into info (iduser, number, property) values (1, 3, 'ggg')");
        $this->dbDriver->execute("insert into info (iduser, number, property) values (3, 20.02, 'bbb')");
    }

    #[Override]
    public function tearDown(): void
    {
        unlink('/tmp/test.db');
    }

    /** @psalm-suppress InvalidArrayOffset */
    public function testGetIterator()
    {
        $iterator = $this->dbDriver->getIterator('select * from info');
        $expected =
            [
                [ 'id'=> 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
                [ 'id'=> 2, 'iduser' => 1, 'number' => 3, 'property' => 'ggg'],
                [ 'id'=> 3, 'iduser' => 3, 'number' => 20.02, 'property' => 'bbb'],
            ];

        // To Array
//        $this->assertEquals(
//            $expected,
//            $iterator->toArray()
//        );

        // While
        $iterator = $this->dbDriver->getIterator('select * from info');
        $i = 0;
        while ($iterator->valid()) {
            $row = $iterator->current();
            $this->assertEquals($expected[$i++], $row->toArray());
            $iterator->next();
        }
        $this->assertEquals(3, $i);

        // Foreach
        $iterator = $this->dbDriver->getIterator('select * from info');
        $i = 0;
        foreach ($iterator as $row) {
            $this->assertEquals($expected[$i++], $row->toArray());
        }
        $this->assertEquals(3, $i);
    }

    /** @psalm-suppress InvalidArrayOffset */
    public function testGetIteratorWithSqlStatement()
    {
        $expected = [
            ['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
            ['id' => 2, 'iduser' => 1, 'number' => 3, 'property' => 'ggg'],
            ['id' => 3, 'iduser' => 3, 'number' => 20.02, 'property' => 'bbb'],
        ];

        // Step 1: Basic SqlStatement
        $sqlStatement = new SqlStatement('select * from info');
        $iterator = $this->dbDriver->getIterator($sqlStatement);
        $this->assertEquals($expected, $iterator->toArray());

        // Step 2: SqlStatement with params in constructor
        $sqlStatement = new SqlStatement('select * from info where id = :id', ['id' => 1]);
        $iterator = $this->dbDriver->getIterator($sqlStatement);
        $this->assertEquals([$expected[0]], $iterator->toArray());

        // Step 3: SqlStatement with params passed separately
        $sqlStatement = new SqlStatement('select * from info where id = :id');
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 2]);
        $this->assertEquals([$expected[1]], $iterator->toArray());
    }

    /** @psalm-suppress InvalidArrayOffset */
    public function testGetIteratorFilter()
    {
        $iterator = $this->dbDriver->getIterator('select * from info where iduser = :id', ['id' => 1]);
        $expected =
            [
                [ 'id'=> 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
                [ 'id'=> 2, 'iduser' => 1, 'number' => 3, 'property' => 'ggg'],
            ];

        // To Array
        $this->assertEquals(
            $expected,
            $iterator->toArray()
        );

        // While
        $iterator = $this->dbDriver->getIterator('select * from info where iduser = :id', ['id' => 1]);
        $i = 0;
        while ($iterator->valid()) {
            $row = $iterator->current();
            $this->assertEquals($expected[$i++], $row->toArray());
            $iterator->next();
        }

        // Foreach
        $iterator = $this->dbDriver->getIterator('select * from info where iduser = :id', ['id' => 1]);
        $i = 0;
        foreach ($iterator as $row) {
            $this->assertEquals($expected[$i++], $row->toArray());
        }
    }

    public function testGetIteratorNotFound()
    {
        $iterator = $this->dbDriver->getIterator('select * from info where iduser = :id', ['id' => 5]);

        // To Array
        $this->assertEquals(
            [],
            $iterator->toArray()
        );

        // While
        $iterator = $this->dbDriver->getIterator('select * from info where iduser = :id', ['id' => 5]);
        $this->assertFalse($iterator->valid());

        // Foreach
        $iterator = $this->dbDriver->getIterator('select * from info where iduser = :id', ['id' => 5]);
        $i = 0;
        foreach ($iterator as $row) {
            $i++;
        }
        $this->assertEquals(0, $i);
    }

    public function testGetScalar()
    {
        $count1 = $this->dbDriver->getScalar('select count(*) from info');
        $this->assertEquals(3, $count1);

        $count2 = $this->dbDriver->getScalar('select count(*) from info where iduser = :id', ['id' => 1]);
        $this->assertEquals(2, $count2);

        $count3 = $this->dbDriver->getScalar('select count(*) from info where iduser = :id', ['id' => 5]);
        $this->assertEquals(0, $count3);
    }

    public function testGetAllFields()
    {
        $this->assertEquals(
            ['id', 'iduser', 'number', 'property'],
            $this->dbDriver->getAllFields('info')
        );
    }

    public function testExecute()
    {
        $this->dbDriver->execute("insert into users (name, createdate) values ('Another', '2017-05-11')");
        $iterator = $this->dbDriver->getIterator('select * from users where name = :name', ['name' => 'Another']);

        $this->assertEquals(
            [
                ['id' => 4, 'name' => 'Another', 'createdate' => '2017-05-11'],
            ],
            $iterator->toArray()
        );
    }

    public function testExecuteWithSqlStatement()
    {
        // Using SqlStatement
        $sqlStatement = new SqlStatement("insert into users (name, createdate) values (:name, :date)");
        $this->dbDriver->execute(
            $sqlStatement,
            [
                'name' => 'SqlStatement',
                'date' => '2023-01-15'
            ]
        );

        // Verify the record was inserted
        $iterator = $this->dbDriver->getIterator('select * from users where name = :name', ['name' => 'SqlStatement']);
        $this->assertEquals(
            [
                ['id' => 4, 'name' => 'SqlStatement', 'createdate' => '2023-01-15'],
            ],
            $iterator->toArray()
        );
    }

    public function testGetScalarWithSqlStatement()
    {
        // First add some data
        $this->dbDriver->execute("insert into users (name, createdate) values ('TestScalar', '2023-05-11')");

        // Use SqlStatement for scalar queries
        $sqlStatement = new SqlStatement('select count(*) from users where name = :name');

        // With params in constructor
        $sqlStatementWithParams = new SqlStatement('select count(*) from users where name = :name', ['name' => 'TestScalar']);
        $count = $this->dbDriver->getScalar($sqlStatementWithParams);
        $this->assertEquals(1, $count);

        // With params passed separately
        $count = $this->dbDriver->getScalar($sqlStatement, ['name' => 'TestScalar']);
        $this->assertEquals(1, $count);

        // Non-existent value should return 0
        $count = $this->dbDriver->getScalar($sqlStatement, ['name' => 'DoesNotExist']);
        $this->assertEquals(0, $count);

        // Get actual values
        $sqlStatement = new SqlStatement('select id from users where name = :name');
        $id = $this->dbDriver->getScalar($sqlStatement, ['name' => 'TestScalar']);
        $this->assertEquals(4, $id);
    }

    public function testExecuteAndGetId()
    {
        $newId = $this->dbDriver->executeAndGetId("insert into users (name, createdate) values ('Another', '2017-05-11')");

        $this->assertEquals(4, $newId);
        $iterator = $this->dbDriver->getIterator('select * from users where name = :name', ['name' => 'Another']);

        $this->assertEquals(
            [
                ['id' => 4, 'name' => 'Another', 'createdate' => '2017-05-11'],
            ],
            $iterator->toArray()
        );
    }

    public function testExecuteAndGetIdWithSqlStatement()
    {
        // First with direct SQL
        $newId = $this->dbDriver->executeAndGetId("insert into users (name, createdate) values ('Another', '2017-05-11')");
        $this->assertEquals(4, $newId);

        // Then with SqlStatement
        $sqlStatement = new SqlStatement("insert into users (name, createdate) values (:name, :date)");
        $newId = $this->dbDriver->executeAndGetId(
            $sqlStatement,
            [
                'name' => 'Another2',
                'date' => '2017-06-11'
            ]
        );
        $this->assertEquals(5, $newId);

        // Verify both records were inserted
        $iterator = $this->dbDriver->getIterator('select * from users where id in (4, 5) order by id');
        $this->assertEquals(
            [
                ['id' => 4, 'name' => 'Another', 'createdate' => '2017-05-11'],
                ['id' => 5, 'name' => 'Another2', 'createdate' => '2017-06-11'],
            ],
            $iterator->toArray()
        );
    }

    public function testGetDbHelper()
    {
        $helper = $this->dbDriver->getDbHelper();
        $this->assertInstanceOf(DbSqliteFunctions::class, $helper);
    }

    public function testTransaction()
    {
        $this->dbDriver->beginTransaction();
        $newId = $this->dbDriver->executeAndGetId("insert into users (name, createdate) values ('Another', '2017-05-11')");
        $this->assertEquals(4, $newId);
        $this->dbDriver->commitTransaction();

        $iterator = $this->dbDriver->getIterator('select * from users where name = :name', ['name' => 'Another']);

        $this->assertEquals(
            [
                ['id' => 4, 'name' => 'Another', 'createdate' => '2017-05-11'],
            ],
            $iterator->toArray()
        );
    }

    public function testTransaction2()
    {
        $this->dbDriver->beginTransaction();
        $newId = $this->dbDriver->executeAndGetId("insert into users (name, createdate) values ('Another', '2017-05-11')");
        $this->assertEquals(4, $newId);
        $this->dbDriver->rollbackTransaction();

        $iterator = $this->dbDriver->getIterator('select * from users where name = :name', ['name' => 'Another']);
        $this->assertFalse($iterator->valid());
    }


    public function testTransactionTwoContext()
    {
        // Context 1
        $this->dbDriver->beginTransaction();
        $newId = $this->dbDriver->executeAndGetId("insert into users (name, createdate) values ('Another', '2017-05-11')");
        $this->assertEquals(4, $newId);
        $this->dbDriver->rollbackTransaction();

        // Context 2
        $context2 = Factory::getDbInstance('sqlite:///tmp/test.db');
        $context2->beginTransaction();
        $newId = $context2->executeAndGetId("insert into users (name, createdate) values ('Another2', '2017-04-11')");
        $this->assertEquals(4, $newId);
        $context2->commitTransaction();

        // Check values
        $iterator = $this->dbDriver->getIterator('select * from users where name = :name', ['name' => 'Another']);
        $this->assertFalse($iterator->valid());

        $iterator = $this->dbDriver->getIterator('select * from users where name = :name', ['name' => 'Another2']);
        $this->assertEquals(
            [
                ['id' => 4, 'name' => 'Another2', 'createdate' => '2017-04-11'],
            ],
            $iterator->toArray()
        );
    }

    public function testGetDbConnection()
    {
        $connection = $this->dbDriver->getDbConnection();
        $this->assertInstanceOf(PDO::class, $connection);
    }

    public function testGetUri()
    {
        $uri = $this->dbDriver->getUri();
        $this->assertInstanceOf(Uri::class, $uri);
        $this->assertEquals('sqlite:///tmp/test.db', $uri->__toString());
    }

    // public function testSetAttribute()
    // {
    //     $this->assertNotEquals(\PDO::CASE_UPPER, $this->dbDriver->getAttribute(\PDO::ATTR_CASE));
    //     $this->dbDriver->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_UPPER);
    //     $this->assertEquals(\PDO::CASE_UPPER, $this->dbDriver->getAttribute(\PDO::ATTR_CASE));
    // }

    public function testisSupportMultRowset()
    {
        $this->assertFalse($this->dbDriver->isSupportMultiRowset());
    }

    public function testCachedResults()
    {
        $cache = new ArrayCacheEngine();

        // Get the first from Db and then cache it;
        $sqlStatement = new SqlStatement('select * from info where id = :id');
        $sqlStatement->withCache($cache, 'info', 60);
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 1]);
        $this->assertEquals(
            [
                ['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
            ],
            $iterator->toArray()
        );

        // Remove it from DB (Still in cache) - Execute don't use cache
        $this->dbDriver->execute("delete from users where id = :name", ['id' => 1]);

        // Try get from cache
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 1]);
        $this->assertEquals(
            [
                ['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
            ],
            $iterator->toArray()
        );
    }

    public function testCachedResults1()
    {
        $cache = new ArrayCacheEngine();

        // Get the first from Db and then cache it;
        $sqlStatement = new SqlStatement('select * from info where id = :id');
        $sqlStatement->withCache($cache, 'info_results_1', 60);
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEquals(
            [],
            $iterator->toArray()
        );

        // Add a new record to DB
        $id = $this->dbDriver->execute("insert into info (iduser, number, property) values (2, 20, 40)");
        $this->assertEquals(4, $id);

        // Check if the record is there
        $sqlStatementNoCache = new SqlStatement('select * from info where id = :id');
        $iterator = $this->dbDriver->getIterator($sqlStatementNoCache, ['id' => 4]);
        $this->assertEquals(
            [
                ["id" => 4, "iduser" => 2, "number" => 20, "property" => '40'],
            ],
            $iterator->toArray()
        );

        // Get from cache, should return the same values as before the insert
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEquals(
            [],
            $iterator->toArray()
        );
    }

    public function testCachedResultsqlStatement()
    {
        $cache = new ArrayCacheEngine();

        $sqlStatement = new SqlStatement('select * from info where id = :id');

        // Get the first from Db and then cache it;
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEmpty($iterator->toArray());
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 5]);
        $this->assertEmpty($iterator->toArray());

        // Add a new record to DB
        $id = $this->dbDriver->execute("insert into info (iduser, number, property) values (2, 20, 40)");
        $id = $this->dbDriver->execute("insert into info (iduser, number, property) values (3, 30, 60)");
        $this->assertEquals(4, $id);

        // Get Without Cache
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEquals(
            [
                ["id" => 4, "iduser" => 2, "number" => 20, "property" => '40'],
            ],
            $iterator->toArray()
        );
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 5]);
        $this->assertEquals(
            [
                ["id" => 5, "iduser" => 3, "number" => 30, "property" => '60'],
            ],
            $iterator->toArray()
        );

        // Set up cache
        $sqlStatement->withCache($cache, 'info', 60);

        // Get with cache, should populate the cache
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEquals(
            [
                ["id" => 4, "iduser" => 2, "number" => 20, "property" => '40'],
            ],
            $iterator->toArray()
        );
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 5]);
        $this->assertEquals(
            [
                ["id" => 5, "iduser" => 3, "number" => 30, "property" => '60'],
            ],
            $iterator->toArray()
        );

        // Delete the records
        $id = $this->dbDriver->execute("delete from info where id = :id", ['id' => 4]);
        $id = $this->dbDriver->execute("delete from info where id = :id", ['id' => 5]);

        // Get from cache, should return the same values
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEquals(
            [
                ["id" => 4, "iduser" => 2, "number" => 20, "property" => '40'],
            ],
            $iterator->toArray()
        );
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 5]);
        $this->assertEquals(
            [
                ["id" => 5, "iduser" => 3, "number" => 30, "property" => '60'],
            ],
            $iterator->toArray()
        );

        // Get direct from DB by disabling cache, should return empty
        $sqlStatement->withoutCache();
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEmpty($iterator->toArray());
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 5]);
        $this->assertEmpty($iterator->toArray());
    }

    public function testCachedResults2()
    {
        $cache = new ArrayCacheEngine();

        // Try get from cache (still return the same values)
        $sqlStatement = new SqlStatement('select * from info where id = :id');
        $sqlStatement->withCache($cache, 'info_results_2', 60);
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 1]);
        $this->assertEquals(
            [
                ['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
            ],
            $iterator->toArray()
        );

        // Update a record to DB
        $this->dbDriver->execute("update info set number = 1500 where id = :id", ["id" => 1]);

        // Check if the record is there
        $sqlStatementNoCache = new SqlStatement('select * from info where id = :id');
        $iterator = $this->dbDriver->getIterator($sqlStatementNoCache, ['id' => 1]);
        $this->assertEquals(
            [
                ["id" => 1, "iduser" => 1, "number" => 1500, "property" => 'xxx'],
            ],
            $iterator->toArray()
        );

        // Get from cache, should return the same values as before the update
        $iterator = $this->dbDriver->getIterator($sqlStatement, ['id' => 1]);
        $this->assertEquals(
            [
                ['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
            ],
            $iterator->toArray()
        );
    }

    public function testPDOStatement()
    {
        $pdo = $this->dbDriver->getDbConnection();
        $stmt = $pdo->prepare('select * from info where id = :id');
        $stmt->execute(['id' => 1]);

        $iterator = $this->dbDriver->getDriverIterator($stmt);
        $this->assertEquals(
            [
                ['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
            ],
            $iterator->toArray()
        );

    }


    /**
     * @return void
     * @psalm-suppress UndefinedMethod
     */
    public function testPreFetch()
    {
        $iterator = $this->dbDriver->getIterator('select * from info where id = :id', ["id" => 1], preFetch: 50);

        $result = $iterator->toArray();

        $this->assertEquals(
            [
                ['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
            ],
            $result
        );
    }

    /**
     * @return void
     * @psalm-suppress UndefinedMethod
     */
    public function testPreFetch2()
    {
        $iterator = $this->dbDriver->getIterator('select * from info where id = :id', ["id" => 50], preFetch: 50);

        $result = $iterator->toArray();

        $this->assertEquals(
            [],
            $result
        );
    }

    /**
     * @return void
     * @psalm-suppress UndefinedMethod
     */
    public function testPreFetchError()
    {
        $iterator = $this->dbDriver->getIterator('select * from info where id = :id', [], preFetch: 50);

        $result = $iterator->toArray();

        $this->assertEquals(
            [],
            $result
        );
    }


    /**
     * @dataProvider dataProviderPreFetch
     * @return void
     * @psalm-suppress UndefinedMethod
     */
    #[DataProvider('dataProviderPreFetch')]
    public function testPreFetchWhile(int $preFetch, array $rows, array $expected, array $expectedCursor)
    {
        $iterator = $this->dbDriver->getIterator('select * from info', preFetch: $preFetch);

        $i = 0;
        while ($iterator->valid()) {
            $row = $iterator->current();
            $this->assertEquals($rows[$i], $row->toArray(), "Row $i");
            $this->assertEquals($i, $iterator->key(), "Key Row $i");
            $i++;
            $iterator->next();
        }
    }


    /**
     * @psalm-suppress UndefinedMethod
     * @return void
     */
    #[DataProvider('dataProviderPreFetch')]
    public function testPreFetchForEach(int $preFetch, array $rows, array $expected, array $expectedCursor)
    {
        $iterator = $this->dbDriver->getIterator('select * from info', preFetch: $preFetch);

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
        $iterator = $this->dbDriver->getIterator('select * from info', preFetch: $preFetch);

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
            ['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
            ['id' => 2, 'iduser' => 1, 'number' => 3, 'property' => 'ggg'],
            ['id' => 3, 'iduser' => 3, 'number' => 20.02, 'property' => 'bbb'],
        ];

        return [
            [0, $rows, [1, 1, 1], [true, true, true]],
            [1, $rows, [1, 1, 1], [true, true, true]],
            [2, $rows, [2, 2, 1], [true, true, false]],
            [3, $rows, [3, 2, 1], [true, false, false]],
            [50, $rows, [3, 2, 1], [false, false, false]],
        ];
    }

    public function testGetIteratorWithEntityClass()
    {
        // Get iterator with entity class
        $iterator = $this->dbDriver->getIterator('select * from info', entityClass: Info::class);

        // Verify we get objects of the correct type
        $i = 0;
        foreach ($iterator as $singleRow) {
            $entity = $singleRow->entity();
            $this->assertInstanceOf(Info::class, $entity);

            // Access array values directly instead of using get()
            $rowArray = $singleRow->toArray();

            // Verify properties were populated correctly
            $this->assertEquals($rowArray['id'], $entity->id);
            $this->assertEquals($rowArray['iduser'], $entity->iduser);
            $this->assertEquals($rowArray['number'], $entity->number);
            $this->assertEquals($rowArray['property'], $entity->property);

            $i++;
        }

        // Verify we got all the expected rows
        $this->assertEquals(3, $i);
    }

    public function testGetIteratorWithEntityClassAndSqlStatement()
    {
        $sqlStatement = new SqlStatement('select * from info');

        // Get iterator with entity class
        $iterator = $this->dbDriver->getIterator($sqlStatement, entityClass: Info::class);

        // Verify we get objects of the correct type
        $i = 0;
        foreach ($iterator as $singleRow) {
            $entity = $singleRow->entity();
            $this->assertInstanceOf(Info::class, $entity);

            // Access array values directly instead of using get()
            $rowArray = $singleRow->toArray();

            // Verify properties were populated correctly
            $this->assertEquals($rowArray['id'], $entity->id);
            $this->assertEquals($rowArray['iduser'], $entity->iduser);
            $this->assertEquals($rowArray['number'], $entity->number);
            $this->assertEquals($rowArray['property'], $entity->property);

            $i++;
        }

        // Verify we got all the expected rows
        $this->assertEquals(3, $i);
    }

    public function testEntityWithTransformer()
    {
        // Get iterator with entity class and transformer
        $iterator = $this->dbDriver->getIterator(
            'select * from users',
            entityClass: UserEntity::class,
            entityTransformer: new PropertyNameMapper(['id' => 'userId', 'name' => 'userName', 'createdate' => 'userCreatedDate'])
        );

        // Verify we get objects of the correct type with transformed property names
        $entities = [];
        foreach ($iterator as $singleRow) {
            $entities[] = $singleRow->entity();
        }

        // Make sure we found rows
        $this->assertCount(3, $entities);

        // Check entity properties - using the first row as an example
        $this->assertInstanceOf(UserEntity::class, $entities[0]);
        $this->assertEquals(1, $entities[0]->userId);
        $this->assertEquals('John Doe', $entities[0]->userName);
        $this->assertEquals('2017-01-02', $entities[0]->userCreatedDate);
    }

    public function testEntityWithComplexTransformer()
    {
        $transformer = new PropertyNameMapper(
            [
                'id' => 'infoId',
                'iduser' => 'userId',
                'number' => 'numericValue',
                'property' => 'infoProperty'
            ],
            function ($sourceField, $targetField, $value) {
                if ($targetField === 'numericValue' && !is_null($value)) {
                    return $value * 2; // Double the numeric value for demonstration
                }
                return $value;
            }
        );

        // Get iterator with entity class and transformer
        $iterator = $this->dbDriver->getIterator(
            'select * from info',
            entityClass: InfoEntity::class,
            entityTransformer: $transformer
        );

        // Verify we get objects of the correct type with transformed property names
        $entities = [];
        foreach ($iterator as $singleRow) {
            $entities[] = $singleRow->entity();
        }

        // Make sure we found rows
        $this->assertCount(3, $entities);

        // Check entity properties - using the first row as an example
        $this->assertInstanceOf(InfoEntity::class, $entities[0]);
        $this->assertEquals(1, $entities[0]->infoId);
        $this->assertEquals(1, $entities[0]->userId);
        $this->assertEqualsWithDelta(20.9, $entities[0]->numericValue, 0.01); // Value should be doubled (10.45 * 2)
        $this->assertEquals('xxx', $entities[0]->infoProperty);
    }
}
