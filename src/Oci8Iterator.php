<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Traits\PreFetchTrait;

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

    public function fetchRow(): array|bool
    {
        return oci_fetch_assoc($this->cursor);
    }

    public function isCursorOpen(): bool
    {
        return !is_null($this->cursor);
    }

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
}
