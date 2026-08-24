<?php

namespace Test;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Exception\JournalException;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\Interfaces\DbDriverInterface;
use ByJG\AnyDataset\Db\Journal\JournalOperationEnum;
use ByJG\AnyDataset\Db\Journal\JournalRecorder;
use ByJG\AnyDataset\Db\Journal\JournalRestorer;
use Override;
use PHPUnit\Framework\TestCase;

class JournalTest extends TestCase
{
    protected DbDriverInterface $dbDriver;

    protected DatabaseExecutor $executor;

    protected string $dbFile = '/tmp/journal_test.db';

    #[Override]
    public function setUp(): void
    {
        $this->dbDriver = Factory::getDbInstance('sqlite://' . $this->dbFile);
        $this->executor = DatabaseExecutor::using($this->dbDriver);

        $this->executor->execute(
            'create table users (
            id integer primary key autoincrement,
            name varchar(45),
            createdate datetime);'
        );
        $this->executor->execute("insert into users (name, createdate) values ('John Doe', '2017-01-02')");
        $this->executor->execute("insert into users (name, createdate) values ('Jane Doe', '2017-01-04')");
        $this->executor->execute("insert into users (name, createdate) values ('JG', '1974-01-26')");

        $this->executor->execute(
            'create table info (
            id integer primary key autoincrement,
            iduser INTEGER,
            property varchar(45));'
        );
        $this->executor->execute("insert into info (iduser, property) values (1, 'xxx')");
    }

    #[Override]
    public function tearDown(): void
    {
        unset($this->executor);
        unset($this->dbDriver);
        if (file_exists($this->dbFile)) {
            unlink($this->dbFile);
        }
    }

    protected function fetchAll(string $table): array
    {
        $result = [];
        foreach ($this->executor->getIterator("select * from $table order by id") as $row) {
            $result[] = $row->toArray();
        }
        return $result;
    }

    public function testRecordInsert()
    {
        $recorder = JournalRecorder::forAllTables();
        $this->executor->addObserver($recorder);

        $this->executor->execute(
            "insert into users (name, createdate) values (:name, :createdate)",
            ['name' => 'New User', 'createdate' => '2020-05-05']
        );

        $this->assertEquals(1, $recorder->count());
        $entry = $recorder->getEntries()[0];
        $this->assertEquals(JournalOperationEnum::INSERT, $entry->getOperation());
        $this->assertEquals('users', $entry->getTable());
        $this->assertEquals([], $entry->getOldRows());
        $this->assertCount(1, $entry->getNewRows());
        $this->assertEquals('New User', $entry->getNewRows()[0]['name']);
        $this->assertEquals('2020-05-05', $entry->getNewRows()[0]['createdate']);
        $this->assertEquals(4, $entry->getNewRows()[0]['id']);
    }

    public function testRecordUpdate()
    {
        $recorder = JournalRecorder::forAllTables();
        $this->executor->addObserver($recorder);

        $this->executor->execute(
            "update users set name = :name where id = :id",
            ['name' => 'John Doe Jr', 'id' => 1]
        );

        $this->assertEquals(1, $recorder->count());
        $entry = $recorder->getEntries()[0];
        $this->assertEquals(JournalOperationEnum::UPDATE, $entry->getOperation());
        $this->assertEquals('users', $entry->getTable());
        $this->assertCount(1, $entry->getOldRows());
        $this->assertCount(1, $entry->getNewRows());
        $this->assertEquals('John Doe', $entry->getOldRows()[0]['name']);
        $this->assertEquals('John Doe Jr', $entry->getNewRows()[0]['name']);
    }

    public function testRecordUpdateMultipleRows()
    {
        $recorder = JournalRecorder::forAllTables();
        $this->executor->addObserver($recorder);

        $this->executor->execute("update users set name = 'Anonymous'");

        $entry = $recorder->getEntries()[0];
        $this->assertCount(3, $entry->getOldRows());
        $this->assertCount(3, $entry->getNewRows());
        $this->assertEquals(['John Doe', 'Jane Doe', 'JG'], array_column($entry->getOldRows(), 'name'));
        $this->assertEquals(['Anonymous', 'Anonymous', 'Anonymous'], array_column($entry->getNewRows(), 'name'));
    }

    public function testRecordUpdateWhereClauseChangesUpdatedColumn()
    {
        $recorder = JournalRecorder::forAllTables();
        $this->executor->addObserver($recorder);

        // The WHERE clause references the column being changed;
        // the new values must still be captured (selected by primary key).
        $this->executor->execute(
            "update users set name = :newname where name = :oldname",
            ['newname' => 'Johnny', 'oldname' => 'John Doe']
        );

        $entry = $recorder->getEntries()[0];
        $this->assertEquals('John Doe', $entry->getOldRows()[0]['name']);
        $this->assertEquals('Johnny', $entry->getNewRows()[0]['name']);
    }

    public function testRecordDelete()
    {
        $recorder = JournalRecorder::forAllTables();
        $this->executor->addObserver($recorder);

        $this->executor->execute("delete from users where id = :id", ['id' => 2]);

        $this->assertEquals(1, $recorder->count());
        $entry = $recorder->getEntries()[0];
        $this->assertEquals(JournalOperationEnum::DELETE, $entry->getOperation());
        $this->assertCount(1, $entry->getOldRows());
        $this->assertEquals('Jane Doe', $entry->getOldRows()[0]['name']);
        $this->assertEquals([], $entry->getNewRows());
    }

    public function testWatchSingleTable()
    {
        $recorder = JournalRecorder::forTables('info');
        $this->executor->addObserver($recorder);

        $this->executor->execute("insert into users (name) values ('Ignored')");
        $this->executor->execute("insert into info (iduser, property) values (2, 'yyy')");

        $this->assertEquals(1, $recorder->count());
        $this->assertEquals('info', $recorder->getEntries()[0]->getTable());
    }

    public function testNonDmlStatementsAreIgnored()
    {
        $recorder = JournalRecorder::forAllTables();
        $this->executor->addObserver($recorder);

        $this->executor->execute("create table temp_table (id integer primary key)");
        $this->executor->getIterator("select * from users");
        $this->executor->execute("drop table temp_table");

        $this->assertEquals(0, $recorder->count());
    }

    public function testExecuteAndGetIdIsRecorded()
    {
        $recorder = JournalRecorder::forAllTables();
        $this->executor->addObserver($recorder);

        $id = $this->executor->executeAndGetId(
            "insert into users (name, createdate) values (:name, :createdate)",
            ['name' => 'New User', 'createdate' => '2020-05-05']
        );

        $this->assertEquals(4, $id);
        $this->assertEquals(1, $recorder->count());
        $this->assertEquals(4, $recorder->getEntries()[0]->getNewRows()[0]['id']);
    }

    public function testRestoreAfterMixedOperations()
    {
        $before = [
            'users' => $this->fetchAll('users'),
            'info' => $this->fetchAll('info'),
        ];

        $recorder = JournalRecorder::forAllTables();
        $this->executor->addObserver($recorder);

        // Simulate the functional test body
        $this->executor->execute("insert into users (name, createdate) values ('Temp User', '2021-01-01')");
        $this->executor->execute("update users set name = :name where id = :id", ['name' => 'Changed', 'id' => 1]);
        $this->executor->execute("delete from users where id = :id", ['id' => 2]);
        $this->executor->execute("insert into info (iduser, property) values (9, 'to-be-removed')");
        $this->executor->execute("update info set property = 'zzz' where id = 1");

        $this->assertEquals(5, $recorder->count());
        $this->assertNotEquals($before['users'], $this->fetchAll('users'));
        $this->assertNotEquals($before['info'], $this->fetchAll('info'));

        // Simulate tearDown
        $this->executor->removeObserver($recorder);
        (new JournalRestorer())->restore($this->dbDriver, $recorder);

        $this->assertEquals($before['users'], $this->fetchAll('users'));
        $this->assertEquals($before['info'], $this->fetchAll('info'));
        $this->assertEquals(0, $recorder->count());
    }

    public function testRestoreUpdateOnSameRowMultipleTimes()
    {
        $before = $this->fetchAll('users');

        $recorder = JournalRecorder::forAllTables();
        $this->executor->addObserver($recorder);

        $this->executor->execute("update users set name = 'First Change' where id = 1");
        $this->executor->execute("update users set name = 'Second Change' where id = 1");

        $this->executor->removeObserver($recorder);
        (new JournalRestorer())->restore($this->dbDriver, $recorder);

        $this->assertEquals($before, $this->fetchAll('users'));
    }

    public function testRestoreDeleteReinsertsRowWithSameId()
    {
        $before = $this->fetchAll('users');

        $recorder = JournalRecorder::forAllTables();
        $this->executor->addObserver($recorder);

        $this->executor->execute("delete from users where id = 2");

        $this->executor->removeObserver($recorder);
        (new JournalRestorer())->restore($this->dbDriver, $recorder);

        $this->assertEquals($before, $this->fetchAll('users'));
    }

    public function testRestoreKeepsEntriesWhenClearIsFalse()
    {
        $recorder = JournalRecorder::forAllTables();
        $this->executor->addObserver($recorder);

        $this->executor->execute("delete from users where id = 3");
        $this->executor->removeObserver($recorder);

        (new JournalRestorer())->restore($this->dbDriver, $recorder, clear: false);

        $this->assertEquals(1, $recorder->count());
    }

    public function testClear()
    {
        $recorder = JournalRecorder::forAllTables();
        $this->executor->addObserver($recorder);

        $this->executor->execute("delete from users where id = 3");
        $this->assertEquals(1, $recorder->count());

        $recorder->clear();
        $this->assertEquals(0, $recorder->count());
        $this->assertEquals([], $recorder->getEntries());
    }

    public function testInsertWithLiteralValues()
    {
        $recorder = JournalRecorder::forAllTables();
        $this->executor->addObserver($recorder);

        $this->executor->execute("insert into users (name, createdate) values ('Literal User', '2022-02-02')");

        $entry = $recorder->getEntries()[0];
        $this->assertEquals('Literal User', $entry->getNewRows()[0]['name']);
        $this->assertEquals('2022-02-02', $entry->getNewRows()[0]['createdate']);
    }

    public function testCustomPrimaryKey()
    {
        $this->executor->execute(
            'create table products (
            sku varchar(20) primary key,
            title varchar(45));'
        );
        $this->executor->execute("insert into products (sku, title) values ('ABC', 'Product ABC')");

        $before = $this->fetchAllBy('products', 'sku');

        $recorder = JournalRecorder::forTables('products')
            ->withPrimaryKey('products', 'sku');
        $this->executor->addObserver($recorder);

        $this->executor->execute("update products set title = 'Renamed' where sku = 'ABC'");
        $this->executor->execute(
            "insert into products (sku, title) values (:sku, :title)",
            ['sku' => 'XYZ', 'title' => 'Product XYZ']
        );

        $this->executor->removeObserver($recorder);
        (new JournalRestorer())->restore($this->dbDriver, $recorder);

        $this->assertEquals($before, $this->fetchAllBy('products', 'sku'));
    }

    protected function fetchAllBy(string $table, string $orderBy): array
    {
        $result = [];
        foreach ($this->executor->getIterator("select * from $table order by $orderBy") as $row) {
            $result[] = $row->toArray();
        }
        return $result;
    }

    public function testStrictModeRejectsUnparseableWriteBeforeExecution()
    {
        $before = $this->fetchAll('info');

        $recorder = JournalRecorder::forAllTables()->strict();
        $this->executor->addObserver($recorder);

        try {
            $this->executor->execute("insert into info (iduser, property) select id, name from users");
            $this->fail('Expected JournalException was not thrown');
        } catch (JournalException $ex) {
            $this->assertStringContainsString('unsupported DML shape', $ex->getMessage());
        }

        // The statement must have been blocked BEFORE executing
        $this->assertEquals($before, $this->fetchAll('info'));
        $this->assertEquals(0, $recorder->count());
    }

    public function testStrictModeRejectsMultiStatementSql()
    {
        $recorder = JournalRecorder::forAllTables()->strict();
        $this->executor->addObserver($recorder);

        $this->expectException(JournalException::class);
        $this->executor->execute("delete from users; delete from info");
    }

    public function testStrictModeAllowsNonWriteStatements()
    {
        $recorder = JournalRecorder::forAllTables()->strict();
        $this->executor->addObserver($recorder);

        $this->executor->execute("create table temp_strict (id integer primary key)");
        $this->executor->execute("drop table temp_strict");

        $this->assertEquals(0, $recorder->count());
    }

    public function testStrictModeRecordsSupportedDmlNormally()
    {
        $recorder = JournalRecorder::forAllTables()->strict();
        $this->executor->addObserver($recorder);

        $this->executor->execute("insert into users (name) values ('Strict User')");
        $this->executor->execute("update users set name = 'Changed' where id = 1");
        $this->executor->execute("delete from users where id = 2");

        $this->assertEquals(3, $recorder->count());
    }

    public function testStrictModeRejectsInsertWithUncapturableValues()
    {
        $this->executor->execute(
            'create table products (
            sku varchar(20) primary key,
            title varchar(45));'
        );

        $recorder = JournalRecorder::forTables('products')
            ->withPrimaryKey('products', 'sku')
            ->strict();
        $this->executor->addObserver($recorder);

        // All values are expressions the parser cannot resolve, and the table
        // has no usable auto increment id to fetch the row back.
        $this->expectException(JournalException::class);
        $this->executor->execute("insert into products (sku, title) values (upper('abc'), upper('product'))");
    }

    public function testNonStrictModeSkipsUnparseableWriteSilently()
    {
        $recorder = JournalRecorder::forAllTables();
        $this->executor->addObserver($recorder);

        $this->executor->execute("insert into info (iduser, property) select id, name from users");

        // Executed (3 users copied into info), but not journaled
        $this->assertCount(4, $this->fetchAll('info'));
        $this->assertEquals(0, $recorder->count());
    }
}