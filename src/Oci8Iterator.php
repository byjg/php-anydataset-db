<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\IteratorException;
use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Core\Row;
use ByJG\Serializer\Exception\InvalidArgumentException;

class Oci8Iterator extends GenericIterator
{

    const RECORD_BUFFER = 50;

    private $rowBuffer;
    protected $currentRow = 0;
    protected $moveNextRow = 0;

    /**
     * @var resource Cursor
     */
    private $cursor;

    /**
     *
     * @param resource $cursor
     */
    public function __construct($cursor)
    {
        $this->cursor = $cursor;
        $this->rowBuffer = array();
    }

    /**
     * @access public
     * @return int
     */
    public function count()
    {
        return -1;
    }

    /**
     * @access public
     * @return bool
     * @throws InvalidArgumentException
     */
    public function hasNext()
    {
        if (count($this->rowBuffer) >= Oci8Iterator::RECORD_BUFFER) {
            return true;
        }

        if (is_null($this->cursor)) {
            return (count($this->rowBuffer) > 0);
        }

        $rowArray = oci_fetch_array($this->cursor, OCI_ASSOC + OCI_RETURN_NULLS);
        if (!empty($rowArray)) {
            $rowArray = array_change_key_case($rowArray, CASE_LOWER);
            $singleRow = new Row($rowArray);

            $this->currentRow++;

            // Enfileira o registo
            $this->rowBuffer[] = $singleRow;
            // Traz novos até encher o Buffer
            if (count($this->rowBuffer) < DbIterator::RECORD_BUFFER) {
                $this->hasNext();
            }
            return true;
        }

        oci_free_statement($this->cursor);
        $this->cursor = null;
        return (count($this->rowBuffer) > 0);
    }

    public function __destruct()
    {
        if (!is_null($this->cursor)) {
            oci_free_statement($this->cursor);
            $this->cursor = null;
        }
    }

    /**
     * @return mixed
     * @throws IteratorException
     * @throws InvalidArgumentException
     */
    public function moveNext()
    {
        if (!$this->hasNext()) {
            throw new IteratorException("No more records. Did you used hasNext() before moveNext()?");
        } else {
            $row = array_shift($this->rowBuffer);
            $this->moveNextRow++;
            return $row;
        }
    }

    public function key()
    {
        return $this->moveNextRow;
    }
}
