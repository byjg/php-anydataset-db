<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\IsolationLevelEnum;

class DbMysqlFunctions extends DbBaseFunctions
{

    public function __construct()
    {
        $this->deliFieldLeft = '`';
        $this->deliFieldRight = '`';
        $this->deliTableLeft = '`';
        $this->deliTableRight = '`';
    }

    public function concat($str1, $str2 = null)
    {
        return "concat(" . implode(', ', func_get_args()) . ")";
    }

    /**
     * Given a SQL returns it with the proper LIMIT or equivalent method included
     * @param string $sql
     * @param int $start
     * @param int $qty
     * @return string
     */
    public function limit($sql, $start, $qty = null)
    {
        if (is_null($qty)) {
            $qty = 50;
        }

        if (stripos($sql, ' LIMIT ') === false) {
            $sql = $sql . " LIMIT x, y";
        }

        return preg_replace(
            '~(\s[Ll][Ii][Mm][Ii][Tt])\s.*?,\s*.*~',
            '$1 ' . $start .', ' .$qty,
            $sql
        );
    }

    /**
     * Given a SQL returns it with the proper TOP or equivalent method included
     * @param string $sql
     * @param int $qty
     * @return string
     */
    public function top($sql, $qty)
    {
        return $this->limit($sql, 0, $qty);
    }

    /**
     * Return if the database provider have a top or similar function
     * @return bool
     */
    public function hasTop()
    {
        return true;
    }

    /**
     * Return if the database provider have a limit function
     * @return bool
     */
    public function hasLimit()
    {
        return true;
    }

    /**
     * Format date column in sql string given an input format that understands Y M D
     *
     * @param string $format
     * @param string|null $column
     * @return string
     * @example $db->getDbFunctions()->SQLDate("d/m/Y H:i", "dtcriacao")
     */
    public function sqlDate($format, $column = null)
    {
        if (is_null($column)) {
            $column = 'now()';
        }

        $pattern = [
            'Y' => "%Y",
            'y' => "%y",
            'M' => "%b",
            'm' => "%m",
            'Q' => "",
            'q' => "",
            'D' => "%d",
            'd' => "%e",
            'h' => "%I",
            'H' => "%H",
            'i' => "%i",
            's' => "%s",
            'a' => "%p",
            'A' => "%p",
        ];

        $preparedSql = $this->prepareSqlDate($format, $pattern, '');

        return sprintf(
            "DATE_FORMAT(%s,'%s')",
            $column,
            implode('', $preparedSql)
        );
    }

    /**
     *
     * @param DbDriverInterface $dbdataset
     * @param string $sql
     * @param array $param
     * @return int
     */
    public function executeAndGetInsertedId(DbDriverInterface $dbdataset, $sql, $param)
    {
        $returnedId = parent::executeAndGetInsertedId($dbdataset, $sql, $param);
        $iterator = $dbdataset->getIterator("select LAST_INSERT_ID() id");
        if ($iterator->hasNext()) {
            $singleRow = $iterator->moveNext();
            $returnedId = $singleRow->get("id");
        }

        return $returnedId;
    }

    public function hasForUpdate()
    {
        return true;
    }

    public function getTableMetadata(DbDriverInterface $dbdataset, $tableName)
    {
        $sql = "EXPLAIN " . $this->deliTableLeft . $tableName . $this->deliTableRight;
        return $this->getTableMetadataFromSql($dbdataset, $sql);
    }

    protected function parseColumnMetadata($metadata)
    {
        $return = [];

        foreach ($metadata as $key => $value) {
            $return[strtolower($value['field'])] = [
                'name' => $value['field'],
                'dbType' => strtolower($value['type']),
                'required' => $value['null'] == 'NO',
                'default' => $value['default'],
            ] + $this->parseTypeMetadata(strtolower($value['type']));
        }

        return $return;
    }

    public function getIsolationLevelCommand($isolationLevel)
    {
        switch ($isolationLevel) {
            case IsolationLevelEnum::READ_UNCOMMITTED:
                return "SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED";
            case IsolationLevelEnum::READ_COMMITTED:
                return "SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED";
            case IsolationLevelEnum::REPEATABLE_READ:
                return "SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ";
            case IsolationLevelEnum::SERIALIZABLE:
                return "SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE";
            default:
                return "";
        }
    }
}
