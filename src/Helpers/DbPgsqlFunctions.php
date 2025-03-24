<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\AnyDataset\Db\SqlStatement;
use Override;

class DbPgsqlFunctions extends DbBaseFunctions
{

    public function __construct()
    {
        $this->deliFieldLeft = '"';
        $this->deliFieldRight = '"';
        $this->deliTableLeft = '"';
        $this->deliTableRight = '"';
    }

    #[Override]
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
    #[Override]
    public function limit(string $sql, int $start, int $qty = 50): string
    {
        if (stripos($sql, ' LIMIT ') === false) {
            return $sql . " LIMIT $qty OFFSET $start";
        }

        $result = preg_replace(
            '~(\s[Ll][Ii][Mm][Ii][Tt])\s.*?\s([Oo][Ff][Ff][Ss][Ee][Tt])\s.*~',
            '$1 ' . $qty . ' $2 ' . $start,
            $sql
        );
        return $result !== null ? $result : $sql;
    }

    /**
     * Given a SQL returns it with the proper TOP or equivalent method included
     * @param string $sql
     * @param int $qty
     * @return string
     */
    #[Override]
    public function top(string $sql, int $qty): string
    {
        return $this->limit($sql, 0, $qty);
    }

    /**
     * Return if the database provider have a top or similar function
     * @return bool
     */
    #[Override]
    public function hasTop(): bool
    {
        return true;
    }

    /**
     * Return if the database provider have a limit function
     * @return bool
     */
    #[Override]
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
    #[Override]
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
     * @param DbDriverInterface $dbDriver
     * @param string|SqlStatement $sql
     * @param array|null $param
     * @return mixed
     */
    #[Override]
    public function executeAndGetInsertedId(DbDriverInterface $dbDriver, string|SqlStatement $sql, ?array $param = null): mixed
    {
        parent::executeAndGetInsertedId($dbDriver, $sql, $param);
        return $dbDriver->getScalar('select lastval()');
    }

    #[Override]
    public function hasForUpdate(): bool
    {
        return true;
    }

    #[Override]
    public function getTableMetadata(DbDriverInterface $dbdataset, string $tableName): array
    {
        $tableName = strtolower($tableName);
        $sql = "select column_name, data_type || '(' || coalesce(cast(character_maximum_length as varchar), cast(numeric_precision_radix as varchar) || ',' || numeric_scale) || ')' as type, column_default, is_nullable from INFORMATION_SCHEMA.COLUMNS where table_name = '$tableName' ";
        return $this->getTableMetadataFromSql($dbdataset, $sql);
    }

    #[Override]
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

    #[Override]
    public function getIsolationLevelCommand(?IsolationLevelEnum $isolationLevel = null): string
    {
        return match ($isolationLevel) {
            IsolationLevelEnum::READ_UNCOMMITTED => "SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL READ UNCOMMITTED",
            IsolationLevelEnum::READ_COMMITTED => "SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL READ COMMITTED",
            IsolationLevelEnum::REPEATABLE_READ => "SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL REPEATABLE READ",
            IsolationLevelEnum::SERIALIZABLE => "SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL SERIALIZABLE",
            default => "",
        };
    }

    #[Override]
    public function getJoinTablesUpdate(array $tables): array
    {
        $joinTables = [];
        $join = "FROM";
        foreach ($tables as $table) {
            $joinTables[] = " $join " . $this->deliTableLeft . $table['table'] . $this->deliTableRight . " ON " . $table['condition'];
            $join = " INNER JOIN ";
        }

        return [
            "position" => "after_set",
            "sql" => implode(' ', $joinTables)
        ];
    }

}
