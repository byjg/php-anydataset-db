<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;

abstract class GenericDbIterator extends GenericIterator
{
    abstract public function isCursorOpen(): bool;

    abstract public function getPreFetchBufferSize(): int;
}