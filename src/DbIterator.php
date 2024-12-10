<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Traits\PreFetchTrait;
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
        if (!is_null($this->statement)) {
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
}
