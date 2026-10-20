<?php

namespace ByJG\AnyDataset\Db;

/**
 * Events fired by the DatabaseExecutor that can be observed
 * through the DatabaseEventObserverInterface.
 */
enum DatabaseEventTypeEnum
{
    /** Fired before a query (getIterator/getScalar) is executed against the database. Cache hits do not fire this event. */
    case BeforeQuery;

    /** Fired after a query (getIterator/getScalar) was executed against the database. Cache hits do not fire this event. */
    case AfterQuery;

    /** Fired before a command (execute/executeAndGetId) is executed against the database. */
    case BeforeExecute;

    /** Fired after a command (execute/executeAndGetId) was executed against the database. */
    case AfterExecute;
}