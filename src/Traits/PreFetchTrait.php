<?php

namespace ByJG\AnyDataset\Db\Traits;

use ByJG\AnyDataset\Core\Row;

trait PreFetchTrait
{
    protected int $currentRow = -1;
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

    public function hasNext(): bool
    {
        if (count($this->rowBuffer) > 0) {
            return true;
        }

        if ($this->isCursorOpen()) {
            return $this->preFetch();
        }

        $this->releaseCursor();

        return false;
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

            // Enfileira o registo
            $this->rowBuffer[] = $singleRow;
            // Traz novos até encher o Buffer
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

    /**
     * @return Row|null
     */
    public function moveNext(): ?Row
    {
        if (!$this->hasNext()) {
            return null;
        }

        $singleRow = array_shift($this->rowBuffer);
        $this->currentRow++;
        $this->preFetch();
        return $singleRow;
    }

    public function key(): int
    {
        return $this->currentRow;
    }
}
