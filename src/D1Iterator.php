<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Db\Traits\PreFetchTrait;
use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;
use Override;

/**
 * Iterates over the rows returned by the Cloudflare D1 HTTP API.
 *
 * The D1 endpoint answers with the whole result set at once, so there is no server-side cursor to
 * keep open. The rows are consumed one at a time anyway, which keeps the PreFetchTrait semantics
 * identical to the cursor based iterators.
 */
class D1Iterator extends GenericDbIterator
{
    use PreFetchTrait;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $rows;

    private ?string $entityClass;

    private ?PropertyHandlerInterface $entityTransformer;

    public function __construct(
        D1Statement $statement,
        int $preFetch = 0,
        ?string $entityClass = null,
        ?PropertyHandlerInterface $entityTransformer = null
    ) {
        $this->rows = $statement->getRows();
        $this->entityClass = $entityClass;
        $this->entityTransformer = $entityTransformer;
        $this->initPreFetch($preFetch);
    }

    #[Override]
    public function isCursorOpen(): bool
    {
        return !is_null($this->rows);
    }

    #[Override]
    protected function fetchRow(): array|bool
    {
        if (empty($this->rows)) {
            return false;
        }

        return array_shift($this->rows);
    }

    #[Override]
    protected function releaseCursor(): void
    {
        $this->rows = null;
    }

    public function __destruct()
    {
        $this->releaseCursor();
    }
}
