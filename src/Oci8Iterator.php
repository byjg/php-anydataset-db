<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Db\Traits\PreFetchTrait;
use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;
use Override;

class Oci8Iterator extends GenericDbIterator
{
    use PreFetchTrait;

    /**
     * @var resource|null Cursor
     */
    private $cursor;

    /**
     * @var string|null
     */
    private ?string $entityClass;

    /**
     * @var PropertyHandlerInterface|null
     */
    private ?PropertyHandlerInterface $entityTransformer;

    /**
     *
     * @param resource $cursor
     * @param int $preFetch
     * @param string|null $entityClass
     * @param PropertyHandlerInterface|null $entityTransformer
     */
    public function __construct($cursor, int $preFetch = 0, ?string $entityClass = null, ?PropertyHandlerInterface $entityTransformer = null)
    {
        $this->cursor = $cursor;
        $this->entityClass = $entityClass;
        $this->entityTransformer = $entityTransformer;
        $this->initPreFetch($preFetch);
    }

    #[Override]
    public function fetchRow(): array|bool
    {
        if ($this->cursor === null) {
            return false;
        }
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
        if (!is_null($this->cursor)) {
            oci_free_statement($this->cursor);
        }
        $this->cursor = null;
    }

    public function __destruct()
    {
        if (!is_null($this->cursor)) {
            $this->releaseCursor();
        }
    }
}
