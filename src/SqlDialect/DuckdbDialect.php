<?php

namespace ByJG\AnyDataset\Db\SqlDialect;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\AnyDataset\Db\SqlStatement;
use Override;

/**
 * DuckDB's SQL follows PostgreSQL's, so this dialect keeps PgsqlDialect's quoting, LIMIT and
 * concatenation, and replaces what DuckDB does not have: lastval(), TO_CHAR(), FOR UPDATE,
 * isolation levels and the pg_catalog tables used for indexes and foreign keys.
 */
class DuckdbDialect extends PgsqlDialect
{
    /**
     * DuckDB has no lastval(): like Oracle, the id is read from the sequence in the default value
     * of the table's primary key, after the insert.
     */
    #[Override]
    public function executeAndGetInsertedId(DatabaseExecutor $executor, string|SqlStatement $sql, ?array $param = null): mixed
    {
        $sqlString = $sql instanceof SqlStatement ? $sql->getSql() : $sql;
        preg_match('/INSERT\s+INTO\s+"?([a-zA-Z0-9_]+)"?/i', $sqlString, $matches);

        $sequence = null;
        if (!empty($matches[1])) {
            $default = $executor->getScalar(
                "select c.column_default
                   from information_schema.columns c
                   join duckdb_constraints() k
                     on k.table_name = c.table_name and k.schema_name = c.table_schema
                    and k.constraint_type = 'PRIMARY KEY' and list_contains(k.constraint_column_names, c.column_name)
                  where lower(c.table_name) = lower(:tablename) and c.table_schema = current_schema()",
                ['tablename' => $matches[1]]
            );
            if (is_string($default) && preg_match("/^nextval\('([^']+)'\)$/i", $default, $seq)) {
                $sequence = $seq[1];
            }
        }

        $executor->execute($sql, $param);

        if (is_null($sequence)) {
            return null;
        }

        return $executor->getScalar("select currval('$sequence')");
    }

    #[Override]
    public function getSqlLastInsertId(): string
    {
        return "select null id";
    }

    /**
     * Format date column in sql string given an input format that understands Y M D
     *
     * @param string $format
     * @param string|null $column
     * @return string
     */
    #[Override]
    public function sqlDate(string $format, ?string $column = null): string
    {
        if (is_null($column)) {
            $column = 'current_timestamp';
        }

        // strftime() has no quarter
        $pattern = [
            'Y' => "%Y",
            'y' => "%y",
            'M' => "%B",
            'm' => "%m",
            'Q' => "",
            'q' => "",
            'D' => "%d",
            'd' => "%-d",
            'h' => "%I",
            'H' => "%H",
            'i' => "%M",
            's' => "%S",
            'a' => "%p",
            'A' => "%p",
        ];

        return sprintf(
            "strftime(%s, '%s')",
            $column,
            implode('', $this->prepareSqlDate($format, $pattern, ''))
        );
    }

    #[Override]
    public function hasForUpdate(): bool
    {
        return false;
    }

    /**
     * @throws NotAvailableException
     */
    #[Override]
    public function forUpdate(string $sql): string
    {
        throw new NotAvailableException('FOR UPDATE not available for DuckDB');
    }

    /**
     * DuckDB has a single isolation level (snapshot), and no command to change it.
     */
    #[Override]
    public function getIsolationLevelCommand(?IsolationLevelEnum $isolationLevel = null): string
    {
        return "";
    }

    #[Override]
    public function getTableMetadata(DatabaseExecutor $executor, string $tableName): array
    {
        $sql = "select column_name, data_type || coalesce('(' || coalesce(cast(character_maximum_length as varchar), cast(numeric_precision_radix as varchar) || ',' || numeric_scale) || ')', '') as type, column_default, is_nullable,
                       case when exists (
                           select 1 from duckdb_constraints() k
                            where k.constraint_type = 'PRIMARY KEY' and k.schema_name = c.table_schema
                              and k.table_name = c.table_name and list_contains(k.constraint_column_names, c.column_name)
                       ) then 1 else 0 end as is_primary_key,
                       case when c.is_identity = 'YES' or c.column_default like 'nextval(%' then 1 else 0 end as is_auto_increment,
                       case when c.is_generated = 'ALWAYS' then 1 else 0 end as is_generated
                  from information_schema.columns c
                 where lower(c.table_name) = lower(:tablename) and c.table_schema = current_schema()
                 order by c.ordinal_position";
        return $this->parseColumnMetadata($executor->getIterator($sql, ['tablename' => $tableName])->toArray());
    }

    /**
     * duckdb_indexes() gives the indexed expressions as the text of a list, which the cast reads
     * back. A column comes as its name, quoted when it needs to be; an expression starts with "("
     * and is left out, as in PostgreSQL.
     */
    #[Override]
    public function getTableIndexes(DatabaseExecutor $executor, string $tableName): array
    {
        return $this->getTableIndexesFromSql(
            $executor,
            "select index_name, trim(expression, '\"') as column_name, case when is_unique then 1 else 0 end as is_unique
               from (
                   select index_name, is_unique,
                          unnest(cast(expressions as varchar[])) as expression,
                          generate_subscripts(cast(expressions as varchar[]), 1) as position
                     from duckdb_indexes()
                    where lower(table_name) = lower(:tablename) and schema_name = current_schema() and not is_primary
               ) i
              where expression not like '(%'
              order by index_name, position",
            ['tablename' => $tableName]
        );
    }

    #[Override]
    public function getTableForeignKeys(DatabaseExecutor $executor, string $tableName): array
    {
        return $this->getTableForeignKeysFromSql(
            $executor,
            "select constraint_name, column_name, parent_table, parent_column
               from (
                   select constraint_name, referenced_table as parent_table,
                          unnest(constraint_column_names) as column_name,
                          unnest(referenced_column_names) as parent_column,
                          generate_subscripts(constraint_column_names, 1) as position
                     from duckdb_constraints()
                    where constraint_type = 'FOREIGN KEY' and lower(table_name) = lower(:tablename)
                      and schema_name = current_schema()
               ) k
              order by constraint_name, position",
            ['tablename' => $tableName]
        );
    }
}
