---
sidebar_position: 12
---

# Executor Observers

Starting from version 6.0, you can attach observers to a `DatabaseExecutor` to be notified about
queries and commands executed against the database. This is the extension point used by features
such as the [Journal](journal.md), and can also be used for auditing, metrics or custom query logging.

## Events

The events are defined by the `DatabaseEventTypeEnum`:

| Event            | Fired                                                                  |
|------------------|------------------------------------------------------------------------|
| `BEFORE_QUERY`   | Before a query (`getIterator`/`getScalar`) is executed on the database |
| `AFTER_QUERY`    | After a query was executed on the database                             |
| `BEFORE_EXECUTE` | Before a command (`execute`/`executeAndGetId`) is executed             |
| `AFTER_EXECUTE`  | After a command was executed                                           |

Notes:

- Queries answered from the [cache](cache.md) do **not** fire query events (no database round-trip happens).
- `executeAndGetId()` fires the `EXECUTE` events for the INSERT statement and the `QUERY` events
  for the statement retrieving the generated ID.
- Observers are attached to a **specific executor instance**. The deprecated driver methods
  (`$driver->execute()`, `$driver->getIterator()`, etc.) create a fresh `DatabaseExecutor`
  internally on every call, so they bypass your observers. Use the
  [DatabaseExecutor](database-executor.md) API (the deprecated methods are removed in 7.0).

## Creating an Observer

Implement the `DatabaseEventObserverInterface`:

```php
<?php
use ByJG\AnyDataset\Db\DatabaseEvent;
use ByJG\AnyDataset\Db\DatabaseEventTypeEnum;
use ByJG\AnyDataset\Db\Interfaces\DatabaseEventObserverInterface;

class QueryTimer implements DatabaseEventObserverInterface
{
    private array $start = [];
    public array $timings = [];

    public function subscribedEvents(): array
    {
        return [DatabaseEventTypeEnum::BEFORE_QUERY, DatabaseEventTypeEnum::AFTER_QUERY];
    }

    public function handleEvent(DatabaseEvent $event): void
    {
        if ($event->getType() === DatabaseEventTypeEnum::BEFORE_QUERY) {
            $this->start[] = microtime(true);
            return;
        }

        $this->timings[] = [
            'sql' => $event->getStatement()->getSql(),
            'elapsed' => microtime(true) - array_pop($this->start),
        ];
    }
}
```

## Attaching and Detaching

```php
<?php
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;

$executor = DatabaseExecutor::using(Factory::getDbInstance('mysql://user:password@host/database'));

$timer = new QueryTimer();
$executor->addObserver($timer);

$executor->getIterator('SELECT * FROM users');

$executor->removeObserver($timer);
```

## The DatabaseEvent Object

Each notification receives a `DatabaseEvent` with:

- `getType()`: the `DatabaseEventTypeEnum` fired;
- `getStatement()`: the `SqlStatement` (SQL text and parameters);
- `getExecutor()`: the executor that fired the event — observers can use it to run additional
  queries on the same connection (and same transaction);
- `getResult()`: the operation result for `AFTER_*` events (`true` for `execute()`).

## See Also

- [Journal](journal.md) - record INSERT/UPDATE/DELETE with old and new values
- [DatabaseExecutor](database-executor.md)