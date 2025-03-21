<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Traits\PreFetchTrait;
use Override;
use PDO;
use PDOStatement;
use ReturnTypeWillChange;

class DbIterator extends GenericIterator
{
    use PreFetchTrait;

    /**
     * @var PDOStatement|null
     */
    private ?PDOStatement $statement;

    /**
     * @param PDOStatement $recordset
     * @param int $preFetch
     */
    public function __construct(PDOStatement $recordset, int $preFetch = 0)
    {
        $this->statement = $recordset;
        $this->initPreFetch($preFetch);
    }

    /**
     * @return int
     */
    #[ReturnTypeWillChange]
    public function count(): int
    {
        return $this->statement->rowCount();
    }

    public function isCursorOpen(): bool
    {
        return !is_null($this->statement);
    }

    public function releaseCursor(): void
    {
        if ($this->isCursorOpen()) {
            $this->statement->closeCursor();
            $this->statement = null;
        }
    }

    protected function fetchRow(): array|bool
    {
        return $this->statement->fetch(PDO::FETCH_ASSOC);
    }

    public function __destruct()
    {
        $this->releaseCursor();
    }

    #[Override]
    #[ReturnTypeWillChange]
    public function current(): mixed
    {
        if (empty($this->rowBuffer)) {
            return null;
        }

        return $this->rowBuffer[0];
    }

    #[Override]
    #[ReturnTypeWillChange]
    public function next(): void
    {
        if (!empty($this->rowBuffer)) {
            array_shift($this->rowBuffer);
            $this->currentRow++;
            $this->preFetch();
        }
    }

    #[Override]
    #[ReturnTypeWillChange]
    public function valid(): bool
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
