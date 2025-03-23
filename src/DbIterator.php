<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Traits\PreFetchTrait;
use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;
use Override;
use PDO;
use PDOStatement;

class DbIterator extends GenericIterator
{
    use PreFetchTrait;

    /**
     * @var PDOStatement|null
     */
    private ?PDOStatement $statement;

    /**
     * @var string|null
     */
    private ?string $entityClass;

    /**
     * @var PropertyHandlerInterface|null
     */
    private ?PropertyHandlerInterface $entityTransformer;

    /**
     * @param PDOStatement $recordset
     * @param int $preFetch
     * @param string|null $entityClass
     * @param PropertyHandlerInterface|null $entityTransformer
     */
    public function __construct(PDOStatement $recordset, int $preFetch = 0, ?string $entityClass = null, ?PropertyHandlerInterface $entityTransformer = null)
    {
        $this->statement = $recordset;
        $this->entityClass = $entityClass;
        $this->entityTransformer = $entityTransformer;
        $this->initPreFetch($preFetch);
    }

    #[Override]
    public function isCursorOpen(): bool
    {
        return !is_null($this->statement);
    }

    #[Override]
    public function releaseCursor(): void
    {
        if ($this->isCursorOpen()) {
            $this->statement->closeCursor();
            $this->statement = null;
        }
    }

    #[Override]
    protected function fetchRow(): array|bool
    {
        return $this->statement->fetch(PDO::FETCH_ASSOC);
    }

    public function __destruct()
    {
        $this->releaseCursor();
    }
}
