<?php

namespace Test;

use ByJG\AnyDataset\Db\DatabaseEvent;
use ByJG\AnyDataset\Db\DatabaseEventTypeEnum;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\Interfaces\DatabaseEventObserverInterface;
use ByJG\AnyDataset\Db\Interfaces\DbDriverInterface;
use Override;
use PHPUnit\Framework\TestCase;

class ObserverSpy implements DatabaseEventObserverInterface
{
    /** @var DatabaseEvent[] */
    public array $events = [];

    /** @var DatabaseEventTypeEnum[] */
    public array $subscribed;

    public function __construct(?array $subscribed = null)
    {
        $this->subscribed = $subscribed ?? [
            DatabaseEventTypeEnum::BEFORE_QUERY,
            DatabaseEventTypeEnum::AFTER_QUERY,
            DatabaseEventTypeEnum::BEFORE_EXECUTE,
            DatabaseEventTypeEnum::AFTER_EXECUTE,
        ];
    }

    #[Override]
    public function subscribedEvents(): array
    {
        return $this->subscribed;
    }

    #[Override]
    public function handleEvent(DatabaseEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return DatabaseEventTypeEnum[]
     */
    public function eventTypes(): array
    {
        return array_map(fn(DatabaseEvent $event) => $event->getType(), $this->events);
    }
}

class DatabaseExecutorObserverTest extends TestCase
{
    protected DbDriverInterface $dbDriver;

    protected DatabaseExecutor $executor;

    protected string $dbFile = '/tmp/observer_test.db';

    #[Override]
    public function setUp(): void
    {
        $this->dbDriver = Factory::getDbInstance('sqlite://' . $this->dbFile);
        $this->executor = DatabaseExecutor::using($this->dbDriver);

        $this->executor->execute(
            'create table users (
            id integer primary key autoincrement,
            name varchar(45));'
        );
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

    public function testExecuteFiresExecuteEvents()
    {
        $spy = new ObserverSpy();
        $this->executor->addObserver($spy);

        $this->executor->execute("insert into users (name) values (:name)", ['name' => 'John']);

        $this->assertEquals(
            [DatabaseEventTypeEnum::BEFORE_EXECUTE, DatabaseEventTypeEnum::AFTER_EXECUTE],
            $spy->eventTypes()
        );

        $this->assertEquals("insert into users (name) values (:name)", $spy->events[0]->getStatement()->getSql());
        $this->assertEquals(['name' => 'John'], $spy->events[0]->getStatement()->getParams());
        $this->assertNull($spy->events[0]->getResult());
        $this->assertTrue($spy->events[1]->getResult());
        $this->assertSame($this->executor, $spy->events[0]->getExecutor());
    }

    public function testGetIteratorFiresQueryEvents()
    {
        $spy = new ObserverSpy();
        $this->executor->addObserver($spy);

        $this->executor->getIterator("select * from users where id = :id", ['id' => 1]);

        $this->assertEquals(
            [DatabaseEventTypeEnum::BEFORE_QUERY, DatabaseEventTypeEnum::AFTER_QUERY],
            $spy->eventTypes()
        );
        $this->assertEquals("select * from users where id = :id", $spy->events[0]->getStatement()->getSql());
        $this->assertEquals(['id' => 1], $spy->events[0]->getStatement()->getParams());
    }

    public function testExecuteAndGetIdFiresExecuteEvents()
    {
        $spy = new ObserverSpy();
        $this->executor->addObserver($spy);

        $id = $this->executor->executeAndGetId("insert into users (name) values (:name)", ['name' => 'Jane']);

        $this->assertEquals(1, $id);
        // The insert fires execute events; fetching the generated id fires query events.
        $this->assertEquals(
            [
                DatabaseEventTypeEnum::BEFORE_EXECUTE,
                DatabaseEventTypeEnum::AFTER_EXECUTE,
                DatabaseEventTypeEnum::BEFORE_QUERY,
                DatabaseEventTypeEnum::AFTER_QUERY,
            ],
            $spy->eventTypes()
        );
    }

    public function testObserverReceivesOnlySubscribedEvents()
    {
        $spy = new ObserverSpy([DatabaseEventTypeEnum::AFTER_EXECUTE]);
        $this->executor->addObserver($spy);

        $this->executor->execute("insert into users (name) values ('John')");
        $this->executor->getIterator("select * from users");

        $this->assertEquals([DatabaseEventTypeEnum::AFTER_EXECUTE], $spy->eventTypes());
    }

    public function testRemoveObserverStopsNotifications()
    {
        $spy = new ObserverSpy();
        $this->executor->addObserver($spy);

        $this->executor->execute("insert into users (name) values ('John')");
        $this->assertCount(2, $spy->events);

        $this->executor->removeObserver($spy);
        $this->executor->execute("insert into users (name) values ('Jane')");
        $this->assertCount(2, $spy->events);
    }

    public function testAddSameObserverTwiceNotifiesOnce()
    {
        $spy = new ObserverSpy();
        $this->executor->addObserver($spy);
        $this->executor->addObserver($spy);

        $this->executor->execute("insert into users (name) values ('John')");

        $this->assertEquals(
            [DatabaseEventTypeEnum::BEFORE_EXECUTE, DatabaseEventTypeEnum::AFTER_EXECUTE],
            $spy->eventTypes()
        );
    }

    public function testMultipleObservers()
    {
        $spy1 = new ObserverSpy([DatabaseEventTypeEnum::BEFORE_EXECUTE]);
        $spy2 = new ObserverSpy([DatabaseEventTypeEnum::AFTER_EXECUTE]);
        $this->executor->addObserver($spy1);
        $this->executor->addObserver($spy2);

        $this->executor->execute("insert into users (name) values ('John')");

        $this->assertEquals([DatabaseEventTypeEnum::BEFORE_EXECUTE], $spy1->eventTypes());
        $this->assertEquals([DatabaseEventTypeEnum::AFTER_EXECUTE], $spy2->eventTypes());
    }
}