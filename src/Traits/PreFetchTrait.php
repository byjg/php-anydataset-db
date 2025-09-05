<?php

namespace ByJG\AnyDataset\Db\Traits;

use ByJG\AnyDataset\Core\Row;
use ByJG\AnyDataset\Core\RowInterface;
use ByJG\MicroOrm\PropertyHandler\MapFromDbToInstanceHandler;
use ByJG\Serializer\ObjectCopy;
use ByJG\Serializer\Serialize;
use Override;
use ReturnTypeWillChange;
use SplDoublyLinkedList;

trait PreFetchTrait
{
    protected int $currentRow = 0;
    protected int $preFetchRows = 0;
    protected SplDoublyLinkedList $rowBuffer;

    protected function initPreFetch(int $preFetch = 0): void
    {
        $this->rowBuffer = new SplDoublyLinkedList();
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

        $rowFetched = $this->fetchRow();
        if (!empty($rowFetched)) {
            $rowFetched = array_change_key_case($rowFetched, CASE_LOWER);

            // Create row based on entityClass if provided
            if (!empty($this->entityClass)) {
                $entityObj = new $this->entityClass();
                // The command below is to get all properties of the class.
                // This will allow to process all properties, even if they are not in the $fieldValues array.
                // Particularly useful for processing the selectFunction.
                $fieldValues = array_merge(Serialize::from($entityObj)->toArray(), $rowFetched);
                ObjectCopy::copy($fieldValues, $entityObj, $this->entityTransformer);
                $rowFetched = $entityObj;
            }
            $singleRow = new Row($rowFetched);

            // Enqueue the record
            $this->rowBuffer->push($singleRow);
            // Fetch new ones until the buffer is full
            return $this->preFetch();
        }

        $this->releaseCursor();

        return false;
    }

    protected function isPreFetchBufferFull(): bool
    {
        if ($this->getPreFetchRows() === 0) {
            return $this->rowBuffer->count() > 0;
        }

        return $this->rowBuffer->count() >= $this->getPreFetchRows();
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
        return $this->rowBuffer->count();
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
            return $this->rowBuffer->bottom() ?? null;
        }

        return null;
    }

    #[ReturnTypeWillChange]
    #[Override]
    public function next(): void
    {
        if (!$this->rowBuffer->isEmpty()) {
            $this->rowBuffer->shift(); // O(1) operation, no reindexing
            $this->currentRow++;
            $this->preFetch();
        }
    }

    #[Override]
    #[ReturnTypeWillChange]
    public function valid(): bool
    {
        return !$this->rowBuffer->isEmpty() || $this->preFetch();
    }
}
