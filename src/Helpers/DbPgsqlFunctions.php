<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\AnyDataset\Db\DbDriverInterface;

class DbPgsqlFunctions extends DbBaseFunctions
{

    public function __construct()
    {
        $this->deliFieldLeft = '"';
        $this->deliFieldRight = '"';
        $this->deliTableLeft = '"';
        $this->deliTableRight = '"';
    }

    public function concat($str1, $str2 = null)
    {
        return implode(' || ', func_get_args());
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
            $sql = $sql . " LIMIT x OFFSET y";
        }

        return preg_replace(
            '~(\s[Ll][Ii][Mm][Ii][Tt])\s.*?\s([Oo][Ff][Ff][Ss][Ee][Tt])\s.*~',
            '$1 ' . $qty .' $2 ' .$start,
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
            $column = 'current_timestamp';
        }

        $pattern = [
            'Y' => "YYYY",
            'y' => "YY",
            'M' => "Mon",
            'm' => "MM",
            'Q' => "Q",
            'q' => "Q",
            'D' => "DD",
            'd' => "DD",
            'h' => "HH",
            'H' => "HH24",
            'i' => "MI",
            's' => "SS",
            'a' => "AM",
            'A' => "AM",
        ];

        return sprintf(
            "TO_CHAR(%s,'%s')",
            $column,
            implode('', $this->prepareSqlDate($format, $pattern, ''))
        );
    }

    /**
     * @param DbDriverInterface $dbdataset
     * @param string $sql
     * @param array $param
     * @return int
     */
    public function executeAndGetInsertedId(DbDriverInterface $dbdataset, $sql, $param)
    {
        parent::executeAndGetInsertedId($dbdataset, $sql, $param);
        return $dbdataset->getScalar('select lastval()');
    }

    public function hasForUpdate()
    {
        return true;
    }

    public function getTableMetadata(DbDriverInterface $dbdataset, $tableName)
    {
        $tableName = strtolower($tableName);
        $sql = "select column_name, data_type || '(' || coalesce(cast(character_maximum_length as varchar), cast(numeric_precision_radix as varchar) || ',' || numeric_scale) || ')' as type, column_default, is_nullable from INFORMATION_SCHEMA.COLUMNS where table_name = '$tableName' ";
        return $this->getTableMetadataFromSql($dbdataset, $sql);
    }

    protected function parseColumnMetadata($metadata)
    {
        $return = [];

        foreach ($metadata as $key => $value) {
            $return[strtolower($value['column_name'])] = [
                'name' => $value['column_name'],
                'dbType' => strtolower($value['type']),
                'required' => $value['is_nullable'] == 'NO',
                'default' => $value['column_default'],
            ] + $this->parseTypeMetadata(strtolower($value['type']));
        }

        return $return;
    }
}
