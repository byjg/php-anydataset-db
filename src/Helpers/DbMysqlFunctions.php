<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\AnyDataset\Db\SqlStatement;
use Override;

class DbMysqlFunctions extends DbBaseFunctions
{

    public function __construct()
    {
        $this->deliFieldLeft = '`';
        $this->deliFieldRight = '`';
        $this->deliTableLeft = '`';
        $this->deliTableRight = '`';
    }

    #[Override]
    public function concat(string $str1, ?string $str2 = null): string
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
    #[Override]
    public function limit(string $sql, int $start, int $qty = 50): string
    {
        if (stripos($sql, ' LIMIT ') === false) {
            return $sql . " LIMIT $start, $qty";
        }

        $result = preg_replace(
            '~(\s[Ll][Ii][Mm][Ii][Tt])\s.*?,\s*.*~',
            '$1 ' . $start .', ' .$qty,
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

    #[Override]
    public function getSqlLastInsertId(): string
    {
        return "select LAST_INSERT_ID() id";
    }

    #[Override]
    public function hasForUpdate(): bool
    {
        return true;
    }

    #[Override]
    public function getTableMetadata(DbDriverInterface $dbdataset, string $tableName): array
    {
        $sql = "EXPLAIN " . $this->deliTableLeft . $tableName . $this->deliTableRight;
        return $this->getTableMetadataFromSql($dbdataset, $sql);
    }

    #[Override]
    protected function parseColumnMetadata(array $metadata): array
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

    #[Override]
    public function getIsolationLevelCommand(?IsolationLevelEnum $isolationLevel = null): string
    {
        return match ($isolationLevel) {
            IsolationLevelEnum::READ_UNCOMMITTED => "SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED",
            IsolationLevelEnum::READ_COMMITTED => "SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED",
            IsolationLevelEnum::REPEATABLE_READ => "SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ",
            IsolationLevelEnum::SERIALIZABLE => "SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE",
            default => "",
        };
    }

    #[Override]
    public function getJoinTablesUpdate(array $tables): array
    {
        $joinTables = [];
        foreach ($tables as $table) {
            if ($table["table"] instanceof SqlStatement) {
                $table["table"] = "({$table["table"]->getSql()})";
            } else {
                $table["table"] = $this->deliTableLeft . $table['table'] . $this->deliTableRight;
            }
            $table["table"] = $table["table"] . (isset($table["alias"]) ? " AS " . $table["alias"] : "");
            $joinTables[] = " INNER JOIN " . $table["table"] . " ON " . $table['condition'];
        }

        return [
            "position" => "before_set",
            "sql" => implode(' ', $joinTables)
        ];
    }
}
