<?php

namespace ByJG\AnyDataset\Db\SqlDialect;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\AnyDataset\Db\SqlStatement;
use Override;

class PgsqlDialect extends BaseSqlDialect
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

    #[Override]
    public function getSqlLastInsertId(): string
    {
        return "select lastval() id";
    }

    #[Override]
    public function hasForUpdate(): bool
    {
        return true;
    }

    #[Override]
    public function getTableMetadata(DatabaseExecutor $executor, string $tableName): array
    {
        $tableName = strtolower($tableName);
        $sql = "select column_name, data_type || coalesce('(' || coalesce(cast(character_maximum_length as varchar), cast(numeric_precision_radix as varchar) || ',' || numeric_scale) || ')', '') as type, column_default, is_nullable,
                       case when exists (
                           select 1 from INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
                             join INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                               on kcu.constraint_schema = tc.constraint_schema and kcu.constraint_name = tc.constraint_name
                            where tc.constraint_type = 'PRIMARY KEY' and tc.table_schema = c.table_schema
                              and tc.table_name = c.table_name and kcu.column_name = c.column_name
                       ) then 1 else 0 end as is_primary_key,
                       case when c.is_identity = 'YES' or c.column_default like 'nextval(%' then 1 else 0 end as is_auto_increment,
                       case when c.is_generated = 'ALWAYS' then 1 else 0 end as is_generated
                  from INFORMATION_SCHEMA.COLUMNS c where table_name = '$tableName' ";
        return $this->getTableMetadataFromSql($executor, $sql);
    }

    #[Override]
    public function getTableIndexes(DatabaseExecutor $executor, string $tableName): array
    {
        return $this->getTableIndexesFromSql(
            $executor,
            "select i.relname as index_name, a.attname as column_name, case when ix.indisunique then 1 else 0 end as is_unique
               from pg_index ix
               join pg_class t on t.oid = ix.indrelid
               join pg_namespace n on n.oid = t.relnamespace
               join pg_class i on i.oid = ix.indexrelid
              cross join lateral unnest(ix.indkey::int2[]) with ordinality as k(attnum, position)
               join pg_attribute a on a.attrelid = t.oid and a.attnum = k.attnum
              where t.relname = :tablename and n.nspname = current_schema() and not ix.indisprimary
              order by i.relname, k.position",
            ['tablename' => strtolower($tableName)]
        );
    }

    #[Override]
    public function getTableForeignKeys(DatabaseExecutor $executor, string $tableName): array
    {
        return $this->getTableForeignKeysFromSql(
            $executor,
            "select con.conname as constraint_name, a.attname as column_name,
                    p.relname as parent_table, pa.attname as parent_column
               from pg_constraint con
               join pg_class t on t.oid = con.conrelid
               join pg_namespace n on n.oid = t.relnamespace
               join pg_class p on p.oid = con.confrelid
              cross join lateral unnest(con.conkey, con.confkey) with ordinality as k(attnum, parent_attnum, position)
               join pg_attribute a on a.attrelid = con.conrelid and a.attnum = k.attnum
               join pg_attribute pa on pa.attrelid = con.confrelid and pa.attnum = k.parent_attnum
              where con.contype = 'f' and t.relname = :tablename and n.nspname = current_schema()
              order by con.conname, k.position",
            ['tablename' => strtolower($tableName)]
        );
    }

    #[Override]
    protected function parseColumnMetadata(array $metadata): array
    {
        $return = [];

        foreach ($metadata as $key => $value) {
            $return[strtolower($value['column_name'])] = $this->newColumnMetadata(
                    name: (string)$value['column_name'],
                    dbType: strtolower($value['type']),
                    required: $value['is_nullable'] == 'NO',
                    default: $value['column_default'],
                    primaryKey: $value['is_primary_key'] == 1,
                    autoIncrement: $value['is_auto_increment'] == 1,
                    generated: $value['is_generated'] == 1
                );
        }

        return $return;
    }

    #[Override]
    public function getIsolationLevelCommand(?IsolationLevelEnum $isolationLevel = null): string
    {
        return match ($isolationLevel) {
            IsolationLevelEnum::ReadUncommitted => "SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL READ UNCOMMITTED",
            IsolationLevelEnum::ReadCommitted => "SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL READ COMMITTED",
            IsolationLevelEnum::RepeatableRead => "SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL REPEATABLE READ",
            IsolationLevelEnum::Serializable => "SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL SERIALIZABLE",
            default => "",
        };
    }

    #[Override]
    public function getJoinTablesUpdate(array $tables): array
    {
        $joinTables = [];
        $join = "FROM";
        foreach ($tables as $table) {
            if ($table["table"] instanceof SqlStatement) {
                $table["table"] = "({$table["table"]->getSql()})";
            } else {
                $table["table"] = $this->deliTableLeft . $table['table'] . $this->deliTableRight;
            }
            $table["table"] = $table["table"] . (isset($table["alias"]) ? " AS " . $table["alias"] : "");
            $joinTables[] = " $join " . $table['table'] . " ON " . $table['condition'];
            $join = " INNER JOIN ";
        }

        return [
            "position" => "after_set",
            "sql" => implode(' ', $joinTables)
        ];
    }
}
