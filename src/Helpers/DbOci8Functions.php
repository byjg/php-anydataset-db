<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\IsolationLevelEnum;

class DbOci8Functions extends DbBaseFunctions
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

        if (stripos($sql, ' OFFSET ') === false && stripos($sql, ' FETCH NEXT ') === false) {
            $sql = $sql . " OFFSET x ROWS FETCH NEXT y ROWS ONLY";
        }

        return preg_replace(
            '~(\s[Oo][Ff][Ff][Ss][Ee][Tt])\s.*?\s([Rr][Oo][Ww][Ss])\s.*?\s([Ff][Ee][Tt][Cc][Hh]\s[Nn][Ee][Xx][Tt])\s.*~',
            '$1 ' . $start . ' $2 ' . '$3 ' . $qty . ' ROWS ONLY',
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
        $dbdataset->execute($sql, $param);
        return 4;
//        oci_inse
//        return $dbdataset->getScalar('select lastval()');
    }

    public function hasForUpdate()
    {
        return true;
    }

    public function getTableMetadata(DbDriverInterface $dbdataset, $tableName)
    {
        $tableName = strtoupper($tableName);
        $sql = "SELECT
                    COLUMN_NAME,
                    DATA_TYPE ||
                        CASE
                            WHEN COALESCE(DATA_PRECISION, CHAR_LENGTH, 0) <> 0
                                THEN '(' || COALESCE(DATA_PRECISION, CHAR_LENGTH) || (
                                    CASE WHEN COALESCE(DATA_SCALE, 0) <> 0 THEN ',' || DATA_SCALE END
                                ) || ')'
                        END AS TYPE,
                    DATA_DEFAULT AS COLUMN_DEFAULT,
                    NULLABLE
                FROM ALL_TAB_COLUMNS WHERE TABLE_NAME = '{$tableName}'";

        return $this->getTableMetadataFromSql($dbdataset, $sql);
    }

    protected function parseColumnMetadata($metadata)
    {
        $return = [];

        foreach ($metadata as $key => $value) {
            $return[strtolower($value['column_name'])] = [
                    'name' => $value['column_name'],
                    'dbType' => strtolower($value['type']),
                    'required' => $value['nullable'] == 'N',
                    'default' => isset($value['column_default']) ? $value['column_default'] : null,
                ] + $this->parseTypeMetadata(strtolower($value['type']));
        }

        return $return;
    }

    public function getIsolationLevelCommand($isolationLevel)
    {
        switch ($isolationLevel) {
            case IsolationLevelEnum::READ_UNCOMMITTED:
                return "SET TRANSACTION READ WRITE";
            case IsolationLevelEnum::READ_COMMITTED:
            case IsolationLevelEnum::REPEATABLE_READ:
                return "SET TRANSACTION ISOLATION LEVEL READ COMMITTED";
            case IsolationLevelEnum::SERIALIZABLE:
                return "SET TRANSACTION ISOLATION LEVEL SERIALIZABLE";
            default:
                return "";
        }
    }
}
