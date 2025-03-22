<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Traits\PreFetchTrait;
use Override;

class Oci8Iterator extends GenericIterator
{
    use PreFetchTrait;

    /**
     * @var resource Cursor
     */
    private $cursor;

    /**
     * @var string|null
     */
    private ?string $entityClass;

    /**
     *
     * @param resource $cursor
     * @param int $preFetch
     * @param string|null $entityClass
     */
    public function __construct($cursor, int $preFetch = 0, ?string $entityClass = null)
    {
        $this->cursor = $cursor;
        $this->entityClass = $entityClass;
        $this->initPreFetch($preFetch);
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
}
