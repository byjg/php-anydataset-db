<?php

namespace ByJG\AnyDataset\Db\Traits;

use ByJG\AnyDataset\Core\Row;
use ByJG\AnyDataset\Core\RowInterface;
use Override;
use ReturnTypeWillChange;

trait PreFetchTrait
{
    protected int $currentRow = 0;
    protected int $preFetchRows = 0;
    protected array $rowBuffer = [];

    protected function initPreFetch(int $preFetch = 0): void
    {
        $this->rowBuffer = [];
        $this->preFetchRows = $preFetch;
        if ($preFetch > 0) {
            $this->preFetch();
        }
    }

    protected function preFetch(): bool
    {
        if ($this->isPreFetchBufferFull()) {
            return true;
        }

        if (!$this->isCursorOpen()) {
            return false;
        }

        $rowArray = $this->fetchRow();
        if (!empty($rowArray)) {
            $rowArray = array_change_key_case($rowArray, CASE_LOWER);
            $singleRow = new Row($rowArray);

            // Enqueue the record
            $this->rowBuffer[] = $singleRow;
            // Fetch new ones until the buffer is full
            return $this->preFetch();
        }

        $this->releaseCursor();

        return false;
    }

    protected function isPreFetchBufferFull(): bool
    {
        if ($this->getPreFetchRows() === 0) {
            return count($this->rowBuffer) > 0;
        }

        return count($this->rowBuffer) >= $this->getPreFetchRows();
    }

    abstract public function isCursorOpen(): bool;

    abstract protected function fetchRow(): array|bool;

    abstract protected function releaseCursor(): void;

    public function getPreFetchRows(): int
    {
        return $this->preFetchRows;
    }

    public function setPreFetchRows(int $preFetchRows): void
    {
        $this->preFetchRows = $preFetchRows;
    }

    public function getPreFetchBufferSize(): int
    {
        return count($this->rowBuffer);
    }

    public function key(): int
    {
        return $this->currentRow;
    }

    #[ReturnTypeWillChange]
    #[Override]
    public function current(): ?RowInterface
    {
        if ($this->valid()) {
            return $this->rowBuffer[0] ?? null;
        }

        return null;
    }

    #[ReturnTypeWillChange]
    #[Override]
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

        return $this->preFetch();
    }
}
