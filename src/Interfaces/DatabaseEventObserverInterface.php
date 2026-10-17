<?php

namespace ByJG\AnyDataset\Db\Interfaces;

use ByJG\AnyDataset\Db\DatabaseEvent;
use ByJG\AnyDataset\Db\DatabaseEventTypeEnum;

/**
 * Observer that can be attached to a DatabaseExecutor to be notified
 * about queries and commands executed against the database.
 *
 * Implement this interface to add behaviors such as journaling, auditing,
 * metrics or query logging without coupling them to the executor.
 */
interface DatabaseEventObserverInterface
{
    /**
     * The list of events this observer wants to receive.
     *
     * @return DatabaseEventTypeEnum[]
     */
    public function subscribedEvents(): array;

    /**
     * Handle a subscribed event.
     *
     * @param DatabaseEvent $event
     * @return void
     */
    public function handleEvent(DatabaseEvent $event): void;
}