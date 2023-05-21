<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\IteratorException;
use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Core\Row;
use PDO;
use PDOStatement;

class DbIterator extends GenericIterator
{

    const RECORD_BUFFER = 50;

    private $rowBuffer;
    private $currentRow = 0;

    /**
     * @var PDOStatement
     */
    private $statement;

    /**
     * @param PDOStatement $recordset
     */
    public function __construct($recordset)
    {
        $this->statement = $recordset;
        $this->rowBuffer = array();
    }

    /**
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return $this->statement->rowCount();
    }

    /**
     * @return bool
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function hasNext()
    {
        if (count($this->rowBuffer) >= DbIterator::RECORD_BUFFER) {
            return true;
        }

        if (is_null($this->statement)) {
            return (count($this->rowBuffer) > 0);
        }

        $rowArray = $this->statement->fetch(PDO::FETCH_ASSOC);
        if (!empty($rowArray)) {
            $singleRow = new Row($rowArray);

            array_push($this->rowBuffer, $singleRow);
            if (count($this->rowBuffer) < DbIterator::RECORD_BUFFER) {
                $this->hasNext();
            }

            return true;
        }

        $this->statement->closeCursor();
        $this->statement = null;

        return (count($this->rowBuffer) > 0);
    }

    /**
     * @return Row
     * @throws IteratorException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function moveNext()
    {
        if (!$this->hasNext()) {
            throw new IteratorException("No more records. Did you used hasNext() before moveNext()?");
        } else {
            $singleRow = array_shift($this->rowBuffer);
            $this->currentRow++;
            return $singleRow;
        }
    }

    public function key()
    {
        return $this->currentRow;
    }
}
