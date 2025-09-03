<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\DbFunctionsInterface;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
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
    abstract public function concat(string $str1, ?string $str2 = null): string;

    /**
     * Given a SQL returns it with the proper LIMIT or equivalent method included
     *
     * @param string $sql
     * @param int $start
     * @param int $qty
     * @return string
     */
    abstract public function limit(string $sql, int $start, int $qty = 50): string;

    /**
     * Given a SQL returns it with the proper TOP or equivalent method included
     *
     * @param string $sql
     * @param int $qty
     * @return string
     */
    abstract public function top(string $sql, int $qty): string;

    /**
     * Return if the database provider have a top or similar function
     *
     * @return bool
     */
    public function hasTop(): bool
    {
        return false;
    }

    /**
     * Return if the database provider have a limit function
     *
     * @return bool
     */
    public function hasLimit(): bool
    {
        return false;
    }

    /**
     * Format date column in sql string given an input format that understands Y M D
     *
     * @param string $format
     * @param string|null $column
     * @return string
     * @example $db->getDbFunctions()->SQLDate("d/m/Y H:i", "dtcriacao")
     */
    abstract public function sqlDate(string $format, ?string $column = null): string;


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
    public function toDate(string $date, string $dateFormat): string
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
    public function fromDate(string $date, string $dateFormat): string
    {
        $dateTime = DateTime::createFromFormat(self::YMDH, $date);

        return $dateTime->format($dateFormat);
    }

    /**
     * @param DbDriverInterface $dbdataset
     * @param string $sql
     * @param array|null $param
     * @return mixed
     */
    public function executeAndGetInsertedId(DbDriverInterface $dbdataset, string $sql, ?array $param = null): mixed
    {
        $dbdataset->execute($sql, $param);

        return $dbdataset->getScalar($this->getSqlLastInsertId());
    }

    public function getSqlLastInsertId(): string
    {
        return "select null id";
    }

    protected $deliFieldLeft = '';
    protected $deliFieldRight = '';
    protected $deliTableLeft = '';
    protected $deliTableRight = '';

    /**
     * @param string|array $field
     * @return string|array
     */
    public function delimiterField(string|array $field): string|array
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

    public function delimiterTable(string|array $table): string
    {
        $tableAr = explode('.', $table);

        return $this->deliTableLeft
            . implode($this->deliTableRight . '.' . $this->deliTableLeft, $tableAr)
            . $this->deliTableRight;
    }

    public function forUpdate(string $sql): string
    {
        if (!preg_match('#\bfor update\b#i', $sql)) {
            $sql = $sql . " FOR UPDATE ";
        }

        return $sql;
    }

    abstract public function hasForUpdate(): bool;

    public function getTableMetadata(DbDriverInterface $dbdataset, string $tableName): array
    {
        throw new Exception("Not implemented");
    }

    protected function getTableMetadataFromSql(DbDriverInterface $dbdataset, $sql)
    {
        $metadata = $dbdataset->getIterator($sql)->toArray();
        return $this->parseColumnMetadata($metadata);
    }

    abstract protected function parseColumnMetadata($metadata);

    protected function parseTypeMetadata($type)
    {
        $matches = [];
        if (!preg_match('/(?<type>[a-z0-9\s]+)(\((?<len>\d+)(,(?<precision>\d+))?\))?/i', $type, $matches)) {
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

    public function getIsolationLevelCommand(?IsolationLevelEnum $isolationLevel = null): string
    {
        return "";
    }

    public function getJoinTablesUpdate(array $tables): array
    {
        return [
            'sql' => '',
            'position' => 'before_set'
        ];
    }
}
