<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Traits\PreFetchTrait;
use Override;
use ReturnTypeWillChange;

class Oci8Iterator extends GenericIterator
{
    use PreFetchTrait;

    /**
     * @var resource Cursor
     */
    private $cursor;

    /**
     *
     * @param resource $cursor
     */
    public function __construct($cursor, int $preFetch = 0)
    {
        $this->cursor = $cursor;
        $this->initPreFetch($preFetch);
    }

    /**
     * @access public
     * @return int
     */
    public function count(): int
    {
        return -1;
    }

    #[Override]
    public function fetchRow(): array|bool
    {
        return oci_fetch_assoc($this->cursor);
    }

    #[Override]
    public function isCursorOpen(): bool
    {
        return !is_null($this->cursor);
    }

    #[Override]
    public function releaseCursor(): void
    {
        oci_free_statement($this->cursor);
        $this->cursor = null;
    }

    public function __destruct()
    {
        if (!is_null($this->cursor)) {
            $this->releaseCursor();
        }
    }

    #[Override] #[ReturnTypeWillChange] public function current(): mixed
    {
        if (empty($this->rowBuffer)) {
            return null;
        }

        return $this->rowBuffer[0];
    }

    #[Override] #[ReturnTypeWillChange] public function next(): void
    {
        if (!empty($this->rowBuffer)) {
            array_shift($this->rowBuffer);
            $this->currentRow++;
            $this->preFetch();
        }
    }

    #[Override] #[ReturnTypeWillChange] public function valid(): bool
    {
        if (count($this->rowBuffer) > 0) {
            return true;
        }

        if ($this->isCursorOpen()) {
            $this->preFetch();
            return count($this->rowBuffer) > 0;
        }

        return false;
    }

    #[Override] #[ReturnTypeWillChange] public function key(): mixed
    {
        return $this->currentRow;
    }
}
