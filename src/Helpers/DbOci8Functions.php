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

    public function concat(string $str1, ?string $str2 = null): string
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
    public function limit(string $sql, int $start, int $qty = 50): string
    {
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
    public function top(string $sql, int $qty): string
    {
        return $this->limit($sql, 0, $qty);
    }

    /**
     * Return if the database provider have a top or similar function
     * @return bool
     */
    public function hasTop(): bool
    {
        return true;
    }

    /**
     * Return if the database provider have a limit function
     * @return bool
     */
    public function hasLimit(): bool
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
    public function sqlDate(string $format, ?string $column = null): string
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
     * @param array|null $param
     * @return mixed
     */
    public function executeAndGetInsertedId(DbDriverInterface $dbdataset, string $sql, ?array $param = null): mixed
    {
        preg_match('/INSERT INTO ([a-zA-Z0-9_]+)/i', $sql, $matches);
        $tableName = $matches[1] ?? null;

        if (!empty($tableName)) {
            $tableName = strtoupper($tableName);

            // Get the primary key of the table
            $primaryKeyResult = $dbdataset->getScalar("SELECT cols.column_name
                FROM all_constraints cons, all_cons_columns cols
                WHERE cols.table_name = '{$tableName}'
                AND cons.constraint_type = 'P'
                AND cons.constraint_name = cols.constraint_name
                AND cons.owner = cols.owner
                AND ROWNUM = 1");

            // Get the default value of the primary key
            $defaultValueResult = $dbdataset->getScalar("SELECT DATA_DEFAULT
                FROM USER_TAB_COLUMNS
                WHERE TABLE_NAME = '{$tableName}'
                AND COLUMN_NAME = '{$primaryKeyResult}'");
        }

        $dbdataset->execute($sql, $param);

        if (!empty($tableName) && !empty($defaultValueResult)) {

            // Check if the default value is a sequence's nextval
            if (strpos($defaultValueResult, '.nextval') !== false) {
                // Extract the sequence name
                $sequenceName = str_replace('.nextval', '', $defaultValueResult);

                // Return the CURRVAL of the sequence
                return $dbdataset->getScalar("SELECT {$sequenceName}.currval FROM DUAL");
            }
        }

        return null;

    }

    public function hasForUpdate(): bool
    {
        return true;
    }

    public function getTableMetadata(DbDriverInterface $dbdataset, string $tableName): array
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

    public function getIsolationLevelCommand(?IsolationLevelEnum $isolationLevel = null): string
    {
        return match ($isolationLevel) {
            IsolationLevelEnum::READ_UNCOMMITTED => "SET TRANSACTION READ WRITE",
            IsolationLevelEnum::READ_COMMITTED, IsolationLevelEnum::REPEATABLE_READ => "SET TRANSACTION ISOLATION LEVEL READ COMMITTED",
            IsolationLevelEnum::SERIALIZABLE => "SET TRANSACTION ISOLATION LEVEL SERIALIZABLE",
            default => "",
        };
    }
}
