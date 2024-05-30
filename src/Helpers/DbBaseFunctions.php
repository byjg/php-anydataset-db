<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\DbFunctionsInterface;
use DateTime;
use Exception;

abstract class DbBaseFunctions implements DbFunctionsInterface
{

    const DMY = "d-m-Y";
    const MDY = "m-d-Y";
    const YMD = "Y-m-d";
    const DMYH = "d-m-Y H:i:s";
    const MDYH = "m-d-Y H:i:s";
    const YMDH = "Y-m-d H:i:s";

    /**
     * Given two or more string the system will return the string containing the proper
     * SQL commands to concatenate these string;
     * use:
     *     for ($i = 0, $numArgs = func_num_args(); $i < $numArgs ; $i++)
     * to get all parameters received.
     *
     * @param string $str1
     * @param string|null $str2
     * @return string
     */
    abstract public function concat($str1, $str2 = null);

    /**
     * Given a SQL returns it with the proper LIMIT or equivalent method included
     *
     * @param string $sql
     * @param int $start
     * @param int $qty
     * @return string
     */
    abstract public function limit($sql, $start, $qty = null);

    /**
     * Given a SQL returns it with the proper TOP or equivalent method included
     *
     * @param string $sql
     * @param int $qty
     * @return string
     */
    abstract public function top($sql, $qty);

    /**
     * Return if the database provider have a top or similar function
     *
     * @return bool
     */
    public function hasTop()
    {
        return false;
    }

    /**
     * Return if the database provider have a limit function
     *
     * @return bool
     */
    public function hasLimit()
    {
        return false;
    }

    /**
     * Format date column in sql string given an input format that understands Y M D
     *
     * @param string $format
     * @param string|bool $column
     * @return string
     * @example $db->getDbFunctions()->SQLDate("d/m/Y H:i", "dtcriacao")
     */
    abstract public function sqlDate($format, $column = null);


    protected function prepareSqlDate($input, $pattern, $delimitString = "'")
    {
        $prepareString = preg_split('/([YyMmQqDdhHisaA])/', $input, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($prepareString as $key => $value) {
            if ('' === $value) {
                unset($prepareString[$key]);
                continue;
            }

            if (isset($pattern[$value])) {
                $formatted = $pattern[$value];
            } else {
                $formatted = $delimitString . $value . $delimitString;
            }
            $prepareString[$key] = $formatted;
        }

        return $prepareString;
    }

    /**
     * Format a string date to a string database readable format.
     *
     * @param string $date
     * @param string $dateFormat
     * @return string
     */
    public function toDate($date, $dateFormat)
    {
        $dateTime = DateTime::createFromFormat($dateFormat, $date);

        return $dateTime->format(self::YMDH);
    }

    /**
     * Format a string database readable format to a string date in a free format.
     *
     * @param string $date
     * @param string $dateFormat
     * @return string
     */
    public function fromDate($date, $dateFormat)
    {
        $dateTime = DateTime::createFromFormat(self::YMDH, $date);

        return $dateTime->format($dateFormat);
    }

    /**
     * @param DbDriverInterface $dbdataset
     * @param string $sql
     * @param array $param
     * @return int
     */
    public function executeAndGetInsertedId(DbDriverInterface $dbdataset, $sql, $param)
    {
        $dbdataset->execute($sql, $param);

        return $dbdataset->getDbConnection()->lastInsertId();
    }

    protected $deliFieldLeft = '';
    protected $deliFieldRight = '';
    protected $deliTableLeft = '';
    protected $deliTableRight = '';

    /**
     * @param array|string $field
     * @return mixed
     */
    public function delimiterField($field)
    {
        $result = [];
        foreach ((array)$field as $fld) {
            $fldAr = explode('.', $fld);
            $result[] = $this->deliFieldLeft
                . implode($this->deliFieldRight . '.' . $this->deliFieldLeft, $fldAr)
                . $this->deliFieldRight;
        }

        if (is_string($field)) {
            return $result[0];
        }

        return $result;
    }

    public function delimiterTable($table)
    {
        $tableAr = explode('.', $table);

        return $this->deliTableLeft
            . implode($this->deliTableRight . '.' . $this->deliTableLeft, $tableAr)
            . $this->deliTableRight;
    }

    public function forUpdate($sql)
    {
        if (!preg_match('#\bfor update\b#i', $sql)) {
            $sql = $sql . " FOR UPDATE ";
        }

        return $sql;
    }

    abstract public function hasForUpdate();

    public function getTableMetadata(DbDriverInterface $dbdataset, $tableName)
    {
        throw new Exception("Not implemented");
    }

    protected function getTableMetadataFromSql(DbDriverInterface $dbdataset, $sql)
    {
        $metadata = $dbdataset->getIterator($sql)->toArray();
        return $this->parseColumnMetadata($metadata);
    }

    protected function parseColumnMetadata($metadata)
    {
        throw new Exception("Not implemented");
    }

    protected function parseTypeMetadata($type)
    {
        $matches = [];
        if (!preg_match('/(?<type>[a-z\s]+)(\((?<len>\d+)(,(?<precision>\d+))?\))?/i', $type, $matches)) {
            return [ 'phpType' => 'string', 'length' => null, 'precision' => null ];
        }

        if (isset($matches['len'])) {
            $matches['len'] = intval($matches['len']);
        } else {
            $matches['len'] = null;
        }

        if (isset($matches['precision'])) {
            $matches['precision'] = intval($matches['precision']);
        } else {
            $matches['precision'] = null;
        }

        if (strpos($matches['type'], 'int') !== false) {
            return [ 'phpType' => 'integer', 'length' => null, 'precision' => null ];
        }

        if (strpos($matches['type'], 'char') !== false || strpos($matches['type'], 'text') !== false) {
            return [ 'phpType' => 'string', 'length' => $matches['len'], 'precision' => null ];
        }

        if (strpos($matches['type'], 'real') !== false || strpos($matches['type'], 'double') !== false || strpos($matches['type'], 'float') !== false || strpos($matches['type'], 'num') !== false || strpos($matches['type'], 'dec') !== false) {
            return [ 'phpType' => 'float', 'length' => $matches['len'], 'precision' => $matches['precision'] ];
        }

        if (strpos($matches['type'], 'bool') !== false) {
            return [ 'phpType' => 'bool', 'length' => null, 'precision' => null ];
        }

        return [ 'phpType' => 'string', 'length' => null, 'precision' => null ];
    }
}
