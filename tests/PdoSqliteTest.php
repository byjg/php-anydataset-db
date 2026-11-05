<?php

namespace Tests;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\Interfaces\DbDriverInterface;
use ByJG\AnyDataset\Db\SqlDialect\SqliteDialect;
use ByJG\AnyDataset\Db\SqlStatement;
use ByJG\Cache\Psr16\ArrayCacheEngine;
use ByJG\Serializer\PropertyHandler\PropertyNameMapper;
use ByJG\Util\Uri;
use Override;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Models\Info;
use Tests\Models\InfoEntity;
use Tests\Models\InfoNumbered;
use Tests\Models\RecordingHydrator;
use Tests\Models\UserEntity;
use Tests\Models\UserTypedEntity;

class PdoSqliteTest extends TestCase
{
    /**
     * @var DbDriverInterface
     */
    protected $dbDriver;

    /**
     * @var DatabaseExecutor
     */
    protected $executor;

    #[Override]
    public function setUp(): void
    {
        $this->dbDriver = Factory::getDbInstance('sqlite:///tmp/test.db');
        $this->executor = DatabaseExecutor::using($this->dbDriver);

        $this->executor->execute(
            'create table users (
            id integer primary key  autoincrement,
            name varchar(45),
            createdate datetime);'
        );
        $this->executor->execute("insert into users (name, createdate) values ('John Doe', '2017-01-02')");
        $this->executor->execute("insert into users (name, createdate) values ('Jane Doe', '2017-01-04')");
        $this->executor->execute("insert into users (name, createdate) values ('JG', '1974-01-26')");


        $this->executor->execute(
            'create table info (
            id integer primary key  autoincrement,
            iduser INTEGER,
            number numeric(10,2),
            property varchar(45));'
        );
        $this->executor->execute("insert into info (iduser, number, property) values (1, 10.45, 'xxx')");
        $this->executor->execute("insert into info (iduser, number, property) values (1, 3, 'ggg')");
        $this->executor->execute("insert into info (iduser, number, property) values (3, 20.02, 'bbb')");
    }

    #[Override]
    public function tearDown(): void
    {
        unlink('/tmp/test.db');
    }

    /** @psalm-suppress InvalidArrayOffset */
    public function testGetIterator()
    {
        $iterator = $this->executor->getIterator('select * from info');
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
        $iterator = $this->executor->getIterator('select * from info');
        $i = 0;
        while ($iterator->valid()) {
            $row = $iterator->current();
            $this->assertEquals($expected[$i++], $row->toArray());
            $iterator->next();
        }
        $this->assertEquals(3, $i);

        // Foreach
        $iterator = $this->executor->getIterator('select * from info');
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
        $iterator = $this->executor->getIterator($sqlStatement);
        $this->assertEquals($expected, $iterator->toArray());

        // Step 2: SqlStatement with params in constructor
        $sqlStatement = new SqlStatement('select * from info where id = :id', ['id' => 1]);
        $iterator = $this->executor->getIterator($sqlStatement);
        $this->assertEquals([$expected[0]], $iterator->toArray());

        // Step 3: SqlStatement with params passed separately
        $sqlStatement = new SqlStatement('select * from info where id = :id');
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 2]);
        $this->assertEquals([$expected[1]], $iterator->toArray());
    }

    /** @psalm-suppress InvalidArrayOffset */
    public function testGetIteratorFilter()
    {
        $iterator = $this->executor->getIterator('select * from info where iduser = :id', ['id' => 1]);
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
        $iterator = $this->executor->getIterator('select * from info where iduser = :id', ['id' => 1]);
        $i = 0;
        while ($iterator->valid()) {
            $row = $iterator->current();
            $this->assertEquals($expected[$i++], $row->toArray());
            $iterator->next();
        }

        // Foreach
        $iterator = $this->executor->getIterator('select * from info where iduser = :id', ['id' => 1]);
        $i = 0;
        foreach ($iterator as $row) {
            $this->assertEquals($expected[$i++], $row->toArray());
        }
    }

    public function testGetIteratorNotFound()
    {
        $iterator = $this->executor->getIterator('select * from info where iduser = :id', ['id' => 5]);

        // To Array
        $this->assertEquals(
            [],
            $iterator->toArray()
        );

        // While
        $iterator = $this->executor->getIterator('select * from info where iduser = :id', ['id' => 5]);
        $this->assertFalse($iterator->valid());

        // Foreach
        $iterator = $this->executor->getIterator('select * from info where iduser = :id', ['id' => 5]);
        $i = 0;
        foreach ($iterator as $row) {
            $i++;
        }
        $this->assertEquals(0, $i);
    }

    public function testGetScalar()
    {
        $count1 = $this->executor->getScalar('select count(*) from info');
        $this->assertEquals(3, $count1);

        $count2 = $this->executor->getScalar('select count(*) from info where iduser = :id', ['id' => 1]);
        $this->assertEquals(2, $count2);

        $count3 = $this->executor->getScalar('select count(*) from info where iduser = :id', ['id' => 5]);
        $this->assertEquals(0, $count3);
    }

    public function testGetAllFields()
    {
        $this->assertEquals(
            ['id', 'iduser', 'number', 'property'],
            $this->executor->getAllFields('info')
        );
    }

    public function testExecute()
    {
        $this->executor->execute("insert into users (name, createdate) values ('Another', '2017-05-11')");
        $iterator = $this->executor->getIterator('select * from users where name = :name', ['name' => 'Another']);

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
        $this->executor->execute(
            $sqlStatement,
            [
                'name' => 'SqlStatement',
                'date' => '2023-01-15'
            ]
        );

        // Verify the record was inserted
        $iterator = $this->executor->getIterator('select * from users where name = :name', ['name' => 'SqlStatement']);
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
        $this->executor->execute("insert into users (name, createdate) values ('TestScalar', '2023-05-11')");

        // Use SqlStatement for scalar queries
        $sqlStatement = new SqlStatement('select count(*) from users where name = :name');

        // With params in constructor
        $sqlStatementWithParams = new SqlStatement('select count(*) from users where name = :name', ['name' => 'TestScalar']);
        $count = $this->executor->getScalar($sqlStatementWithParams);
        $this->assertEquals(1, $count);

        // With params passed separately
        $count = $this->executor->getScalar($sqlStatement, ['name' => 'TestScalar']);
        $this->assertEquals(1, $count);

        // Non-existent value should return 0
        $count = $this->executor->getScalar($sqlStatement, ['name' => 'DoesNotExist']);
        $this->assertEquals(0, $count);

        // Get actual values
        $sqlStatement = new SqlStatement('select id from users where name = :name');
        $id = $this->executor->getScalar($sqlStatement, ['name' => 'TestScalar']);
        $this->assertEquals(4, $id);
    }

    public function testExecuteAndGetId()
    {
        $newId = $this->executor->executeAndGetId("insert into users (name, createdate) values ('Another', '2017-05-11')");

        $this->assertEquals(4, $newId);
        $iterator = $this->executor->getIterator('select * from users where name = :name', ['name' => 'Another']);

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
        $newId = $this->executor->executeAndGetId("insert into users (name, createdate) values ('Another', '2017-05-11')");
        $this->assertEquals(4, $newId);

        // Then with SqlStatement
        $sqlStatement = new SqlStatement("insert into users (name, createdate) values (:name, :date)");
        $newId = $this->executor->executeAndGetId(
            $sqlStatement,
            [
                'name' => 'Another2',
                'date' => '2017-06-11'
            ]
        );
        $this->assertEquals(5, $newId);

        // Verify both records were inserted
        $iterator = $this->executor->getIterator('select * from users where id in (4, 5) order by id');
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
        $helper = $this->executor->getHelper();
        $this->assertInstanceOf(SqliteDialect::class, $helper);
    }

    public function testTransaction()
    {
        $this->executor->beginTransaction();
        $newId = $this->executor->executeAndGetId("insert into users (name, createdate) values ('Another', '2017-05-11')");
        $this->assertEquals(4, $newId);
        $this->executor->commitTransaction();

        $iterator = $this->executor->getIterator('select * from users where name = :name', ['name' => 'Another']);

        $this->assertEquals(
            [
                ['id' => 4, 'name' => 'Another', 'createdate' => '2017-05-11'],
            ],
            $iterator->toArray()
        );
    }

    public function testTransaction2()
    {
        $this->executor->beginTransaction();
        $newId = $this->executor->executeAndGetId("insert into users (name, createdate) values ('Another', '2017-05-11')");
        $this->assertEquals(4, $newId);
        $this->executor->rollbackTransaction();

        $iterator = $this->executor->getIterator('select * from users where name = :name', ['name' => 'Another']);
        $this->assertFalse($iterator->valid());
    }


    public function testTransactionTwoContext()
    {
        // Context 1
        $this->executor->beginTransaction();
        $newId = $this->executor->executeAndGetId("insert into users (name, createdate) values ('Another', '2017-05-11')");
        $this->assertEquals(4, $newId);
        $this->executor->rollbackTransaction();

        // Context 2
        $context2 = Factory::getDbInstance('sqlite:///tmp/test.db');
        $executor2 = DatabaseExecutor::using($context2);
        $executor2->beginTransaction();
        $newId = $executor2->executeAndGetId("insert into users (name, createdate) values ('Another2', '2017-04-11')");
        $this->assertEquals(4, $newId);
        $executor2->commitTransaction();

        // Check values
        $iterator = $this->executor->getIterator('select * from users where name = :name', ['name' => 'Another']);
        $this->assertFalse($iterator->valid());

        $iterator = $this->executor->getIterator('select * from users where name = :name', ['name' => 'Another2']);
        $this->assertEquals(
            [
                ['id' => 4, 'name' => 'Another2', 'createdate' => '2017-04-11'],
            ],
            $iterator->toArray()
        );
    }

    public function testGetDbConnection()
    {
        $connection = $this->executor->getDriver()->getDbConnection();
        $this->assertInstanceOf(PDO::class, $connection);
    }

    public function testGetUri()
    {
        $uri = $this->executor->getDriver()->getUri();
        $this->assertInstanceOf(Uri::class, $uri);
        $this->assertEquals('sqlite:///tmp/test.db', $uri->__toString());
    }

    // public function testSetAttribute()
    // {
    //     $this->assertNotEquals(\PDO::CASE_UPPER, $this->executor->getAttribute(\PDO::ATTR_CASE));
    //     $this->executor->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_UPPER);
    //     $this->assertEquals(\PDO::CASE_UPPER, $this->executor->getAttribute(\PDO::ATTR_CASE));
    // }

    public function testisSupportMultRowset()
    {
        $this->assertFalse($this->executor->getDriver()->isSupportMultiRowset());
    }

    public function testCachedResults()
    {
        $cache = new ArrayCacheEngine();

        // Get the first from Db and then cache it;
        $sqlStatement = new SqlStatement('select * from info where id = :id');
        $sqlStatement->withCache($cache, 'info', 60);
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 1]);
        $this->assertEquals(
            [
                ['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
            ],
            $iterator->toArray()
        );

        // Remove it from DB (Still in cache) - Execute don't use cache
        $this->executor->execute("delete from users where id = :name", ['id' => 1]);

        // Try get from cache
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 1]);
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
        $sqlStatement = SqlStatement::from('select * from info where id = :id')
            ->withCache($cache, 'info_results_1', 60);
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEquals(
            [],
            $iterator->toArray()
        );

        // Add a new record to DB
        $id = $this->executor->execute("insert into info (iduser, number, property) values (2, 20, 40)");
        $this->assertEquals(4, $id);

        // Check if the record is there
        $sqlStatementNoCache = new SqlStatement('select * from info where id = :id');
        $iterator = $this->executor->getIterator($sqlStatementNoCache, ['id' => 4]);
        $this->assertEquals(
            [
                ["id" => 4, "iduser" => 2, "number" => 20, "property" => '40'],
            ],
            $iterator->toArray()
        );

        // Get from cache, should return the same values as before the insert
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 4]);
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
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEmpty($iterator->toArray());
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 5]);
        $this->assertEmpty($iterator->toArray());

        // Add a new record to DB
        $id = $this->executor->execute("insert into info (iduser, number, property) values (2, 20, 40)");
        $id = $this->executor->execute("insert into info (iduser, number, property) values (3, 30, 60)");
        $this->assertEquals(4, $id);

        // Get Without Cache
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEquals(
            [
                ["id" => 4, "iduser" => 2, "number" => 20, "property" => '40'],
            ],
            $iterator->toArray()
        );
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 5]);
        $this->assertEquals(
            [
                ["id" => 5, "iduser" => 3, "number" => 30, "property" => '60'],
            ],
            $iterator->toArray()
        );

        // Set up cache
        $sqlStatement = $sqlStatement->withCache($cache, 'info', 60);

        // Get with cache, should populate the cache
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEquals(
            [
                ["id" => 4, "iduser" => 2, "number" => 20, "property" => '40'],
            ],
            $iterator->toArray()
        );
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 5]);
        $this->assertEquals(
            [
                ["id" => 5, "iduser" => 3, "number" => 30, "property" => '60'],
            ],
            $iterator->toArray()
        );

        // Delete the records
        $id = $this->executor->execute("delete from info where id = :id", ['id' => 4]);
        $id = $this->executor->execute("delete from info where id = :id", ['id' => 5]);

        // Get from cache, should return the same values
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEquals(
            [
                ["id" => 4, "iduser" => 2, "number" => 20, "property" => '40'],
            ],
            $iterator->toArray()
        );
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 5]);
        $this->assertEquals(
            [
                ["id" => 5, "iduser" => 3, "number" => 30, "property" => '60'],
            ],
            $iterator->toArray()
        );

        // Get direct from DB by disabling cache, should return empty
        $sqlStatement = $sqlStatement->withoutCache();
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 4]);
        $this->assertEmpty($iterator->toArray());
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 5]);
        $this->assertEmpty($iterator->toArray());
    }

    public function testCachedResults2()
    {
        $cache = new ArrayCacheEngine();

        // Try get from cache (still return the same values)
        $sqlStatement = SqlStatement::from('select * from info where id = :id')
            ->withCache($cache, 'info_results_2', 60);
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 1]);
        $this->assertEquals(
            [
                ['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
            ],
            $iterator->toArray()
        );

        // Update a record to DB
        $this->executor->execute("update info set number = 1500 where id = :id", ["id" => 1]);

        // Check if the record is there
        $sqlStatementNoCache = new SqlStatement('select * from info where id = :id');
        $iterator = $this->executor->getIterator($sqlStatementNoCache, ['id' => 1]);
        $this->assertEquals(
            [
                ["id" => 1, "iduser" => 1, "number" => 1500, "property" => 'xxx'],
            ],
            $iterator->toArray()
        );

        // Get from cache, should return the same values as before the update
        $iterator = $this->executor->getIterator($sqlStatement, ['id' => 1]);
        $this->assertEquals(
            [
                ['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
            ],
            $iterator->toArray()
        );
    }

    public function testPDOStatement()
    {
        $pdo = $this->executor->getDriver()->getDbConnection();
        $stmt = $pdo->prepare('select * from info where id = :id');
        $stmt->execute(['id' => 1]);

        $iterator = $this->executor->getDriver()->getDriverIterator($stmt);
        $this->assertEquals(
            [
                ['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
            ],
            $iterator->toArray()
        );

    }


    public function testGetIteratorOneRow()
    {
        $iterator = $this->executor->getIterator('select * from info where id = :id', ["id" => 1]);

        $result = $iterator->toArray();

        $this->assertEquals(
            [
                ['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
            ],
            $result
        );
    }

    public function testGetIteratorNoRows()
    {
        $iterator = $this->executor->getIterator('select * from info where id = :id', ["id" => 50]);

        $result = $iterator->toArray();

        $this->assertEquals(
            [],
            $result
        );
    }

    public function testGetIteratorMissingParameter()
    {
        $iterator = $this->executor->getIterator('select * from info where id = :id', []);

        $result = $iterator->toArray();

        $this->assertEquals(
            [],
            $result
        );
    }


    public function testIteratorWhile()
    {
        $rows = $this->infoRows();
        $iterator = $this->executor->getIterator('select * from info');

        $i = 0;
        while ($iterator->valid()) {
            $row = $iterator->current();
            $this->assertEquals($rows[$i], $row->toArray(), "Row $i");
            $this->assertEquals($i, $iterator->key(), "Key Row $i");
            $i++;
            $iterator->next();
        }
    }


    public function testIteratorForEach()
    {
        $rows = $this->infoRows();
        $iterator = $this->executor->getIterator('select * from info');

        $i = 0;
        foreach ($iterator as $row) {
            $this->assertEquals($rows[$i], $row->toArray(), "Row $i");
            $this->assertEquals($i, $iterator->key(), "Key Row $i");
            $this->assertTrue($iterator->isCursorOpen(), "CursorOpen Row $i");
            $i++;
        }
        $this->assertEquals(3, $i);
        $this->assertFalse($iterator->isCursorOpen());
    }

    public function testIteratorPhpIterator()
    {
        $rows = $this->infoRows();
        $iterator = $this->executor->getIterator('select * from info');

        $i = 0;
        while ($iterator->valid()) {
            $row = $iterator->current();
            $this->assertEquals($rows[$i], $row->toArray(), "Row $i");
            $this->assertEquals($i, $iterator->key(), "Key Row $i");
            $this->assertTrue($iterator->isCursorOpen(), "CursorOpen Row $i");
            $i++;
            $iterator->next();
        }
        $this->assertEquals(3, $i);
        $this->assertFalse($iterator->isCursorOpen());
    }

    /**
     * SQLite does not drop a table while a cursor over it is open ("database table is locked")
     */
    public function testCursorIsOpenWhileAPartlyReadIteratorExists()
    {
        $iterator = $this->executor->getIterator('select * from info');
        $this->assertTrue($iterator->valid());

        $this->expectExceptionMessage('database table is locked');
        $this->executor->execute('drop table info');
    }

    public function testGetScalarReleasesTheCursor()
    {
        $this->assertEquals(1, $this->executor->getScalar('select id from info order by id'));

        $this->executor->execute('drop table info');
        $this->assertFalse($this->executor->getScalar("select name from sqlite_master where name = 'info'"));
    }

    public function testFirstReleasesTheCursorWhenTheIteratorIsDestroyed()
    {
        $iterator = $this->executor->getIterator('select * from info order by id');
        $this->assertEquals(['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'], $iterator->first());
        unset($iterator);

        $this->executor->execute('drop table info');
        $this->assertFalse($this->executor->getScalar("select name from sqlite_master where name = 'info'"));
    }

    /**
     * The properties without a column take the values of a new entity read once per query (not one per row): a
     * value the constructor sets for each instance is the same in every row
     */
    public function testPropertyWithoutColumnTakesTheValueOfOneNewEntityPerQuery()
    {
        $sql = (new SqlStatement('select * from info order by id'))->withEntityClass(InfoNumbered::class);

        $entities = $this->executor->getIterator($sql)->toEntities();

        $this->assertSame([1, 2, 3], array_map(fn(InfoNumbered $info) => (int)$info->id, $entities));
        // Each row was a new entity (its own instance number), but took the instance of the one read for the query
        $instances = array_map(fn(InfoNumbered $info) => $info->instance, $entities);
        $this->assertCount(1, array_unique($instances));
        $this->assertLessThan((new InfoNumbered())->instance - 3, $instances[0]);
    }

    public function testEntityTransformerThatHydratesFillsTheEntity()
    {
        $hydrator = new RecordingHydrator();
        $sql = (new SqlStatement('select * from info where id <= 2 order by id'))
            ->withEntityClass(Info::class)
            ->withEntityTransformer($hydrator);

        $entities = $this->executor->getIterator($sql)->toEntities();

        $this->assertSame([1, 2], array_map(fn(Info $info) => $info->id, $entities));
        $this->assertSame(['hydrated:xxx', 'hydrated:ggg'], array_map(fn(Info $info) => $info->property, $entities));
        $this->assertNull($entities[0]->iduser);

        // Each row, a new Info, and the properties of a new Info read once
        $defaults = ['id' => null, 'iduser' => null, 'number' => null, 'property' => null];
        $this->assertEquals([
            [['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'], Info::class, $defaults],
            [['id' => 2, 'iduser' => 1, 'number' => 3, 'property' => 'ggg'], Info::class, $defaults],
        ], $hydrator->calls);
    }

    private function infoRows(): array
    {
        return [
            ['id' => 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
            ['id' => 2, 'iduser' => 1, 'number' => 3, 'property' => 'ggg'],
            ['id' => 3, 'iduser' => 3, 'number' => 20.02, 'property' => 'bbb'],
        ];
    }

    public function testGetIteratorWithEntityClass()
    {
        // Get iterator with entity class
        $sqlStatement = (new SqlStatement('select * from info'))
            ->withEntityClass(Info::class);
        $iterator = $this->executor->getIterator($sqlStatement);

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
        $sqlStatement = (new SqlStatement('select * from info'))
            ->withEntityClass(Info::class);

        // Get iterator with entity class
        $iterator = $this->executor->getIterator($sqlStatement);

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
        $sqlStatement = (new SqlStatement('select * from users'))
            ->withEntityClass(UserEntity::class)
            ->withEntityTransformer(new PropertyNameMapper(['id' => 'userId', 'name' => 'userName', 'createdate' => 'userCreatedDate']));
        // Get iterator with entity class and transformer
        $iterator = $this->executor->getIterator($sqlStatement);

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

    /**
     * Regression: an entity whose typed properties are non-nullable and have no default keeps
     * them "uninitialized" on `new`. GenericDbIterator serializes an empty instance to enumerate the
     * fields before hydration, which used to raise "must not be accessed before initialization".
     */
    public function testEntityWithUninitializedTypedProperties()
    {
        $sqlStatement = (new SqlStatement('select * from users where name = :name'))
            ->withEntityClass(UserTypedEntity::class);

        $iterator = $this->executor->getIterator($sqlStatement, ['name' => 'John Doe']);

        $entities = [];
        foreach ($iterator as $singleRow) {
            $entities[] = $singleRow->entity();
        }

        $this->assertCount(1, $entities);
        $this->assertInstanceOf(UserTypedEntity::class, $entities[0]);
        $this->assertEquals(1, $entities[0]->id);
        $this->assertEquals('John Doe', $entities[0]->name);
        $this->assertEquals('2017-01-02', $entities[0]->createdate);
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

        $sqlStatement = (new SqlStatement('select * from info'))
            ->withEntityClass(InfoEntity::class)
            ->withEntityTransformer($transformer);

        // Get iterator with entity class and transformer
        $iterator = $this->executor->getIterator($sqlStatement);

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
