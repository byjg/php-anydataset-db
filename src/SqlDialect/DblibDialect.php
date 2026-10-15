<?php

namespace ByJG\AnyDataset\Db\SqlDialect;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use Override;

class DblibDialect extends BaseSqlDialect
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
        return implode(' + ', func_get_args());
    }

    /**
     * Given a SQL returns it with the proper LIMIT or equivalent method included
     * @param string $sql
     * @param int $start
     * @param int $qty
     * @return string
     * @throws NotAvailableException
     */
    #[Override]
    public function limit(string $sql, int $start, int $qty = 50): string
    {
        throw new NotAvailableException("DBLib does not support LIMIT feature.");
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
        if (stripos($sql, ' TOP ') === false) {
            $result = preg_replace("/^\\s*(select) /i", "\\1 top $qty ", $sql);
            return $result !== null ? $result : $sql;
        }

        $result = preg_replace(
            '~(\s[Tt][Oo][Pp])\s.*?\d+\s~',
            '$1 ' . $qty . ' ',
            $sql
        );
        return $result !== null ? $result : $sql;
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
        return false;
    }

    /**
     * Format date column in sql string given an input format that understands Y M D
 *
*@param string $format
     * @param string|null $column
     * @return string
     * @example $db->getDbFunctions()->SQLDate("d/m/Y H:i", "dtcriacao")
     */
    #[Override]
    public function sqlDate(string $format, ?string $column = null): string
    {
        if (is_null($column)) {
            $column = "getdate()";
        }

        $pattern = [
            'Y' => "YYYY",
            'y' => "YY",
            'M' => "MM",
            'm' => "M",
            'Q' => "",
            'q' => "",
            'D' => "dd",
            'd' => "dd",
            'h' => "H",
            'H' => "HH",
            'i' => "mm",
            's' => "ss",
            'a' => "",
            'A' => "",
        ];

        $preparedSql = $this->prepareSqlDate($format, $pattern, '');

        return sprintf(
            "FORMAT(%s, '%s')",
            $column,
            implode('', $preparedSql)
        );
    }

    #[Override]
    public function getSqlLastInsertId(): string
    {
        return "select @@identity id";
    }

    /**
     * @param string $sql
     * @return string
     * @throws NotAvailableException
     */
    #[Override]
    public function forUpdate(string $sql): string
    {
        throw new NotAvailableException('FOR UPDATE not available for Mssql/Dblib');
    }

    #[Override]
    public function hasForUpdate(): bool
    {
        return false;
    }

    #[Override]
    public function getTableMetadata(DatabaseExecutor $executor, string $tableName): array
    {
        $sql = "select c.*,
                       COLUMNPROPERTY(OBJECT_ID(QUOTENAME(c.TABLE_SCHEMA) + '.' + QUOTENAME(c.TABLE_NAME)), c.COLUMN_NAME, 'IsIdentity') as is_auto_increment,
                       COLUMNPROPERTY(OBJECT_ID(QUOTENAME(c.TABLE_SCHEMA) + '.' + QUOTENAME(c.TABLE_NAME)), c.COLUMN_NAME, 'IsComputed') as is_generated,
                       case when exists (
                           select 1 from INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
                             join INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                               on kcu.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA and kcu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
                            where tc.CONSTRAINT_TYPE = 'PRIMARY KEY' and tc.TABLE_SCHEMA = c.TABLE_SCHEMA
                              and tc.TABLE_NAME = c.TABLE_NAME and kcu.COLUMN_NAME = c.COLUMN_NAME
                       ) then 1 else 0 end as is_primary_key
                  from INFORMATION_SCHEMA.COLUMNS c where c.TABLE_NAME = '$tableName'";
        return $this->getTableMetadataFromSql($executor, $sql);
    }

    #[Override]
    public function getTableIndexes(DatabaseExecutor $executor, string $tableName): array
    {
        return $this->getTableIndexesFromSql(
            $executor,
            "select i.name as index_name, col.name as column_name, cast(i.is_unique as int) as is_unique
               from sys.indexes i
               join sys.index_columns ic on ic.object_id = i.object_id and ic.index_id = i.index_id
               join sys.columns col on col.object_id = ic.object_id and col.column_id = ic.column_id
              where i.object_id = OBJECT_ID(:tablename) and i.is_primary_key = 0 and ic.is_included_column = 0
              order by i.name, ic.key_ordinal",
            ['tablename' => $tableName]
        );
    }

    #[Override]
    public function getTableForeignKeys(DatabaseExecutor $executor, string $tableName): array
    {
        return $this->getTableForeignKeysFromSql(
            $executor,
            "select fk.name as constraint_name, col.name as column_name,
                    parent.name as parent_table, parent_col.name as parent_column
               from sys.foreign_keys fk
               join sys.foreign_key_columns fkc on fkc.constraint_object_id = fk.object_id
               join sys.columns col on col.object_id = fkc.parent_object_id and col.column_id = fkc.parent_column_id
               join sys.tables parent on parent.object_id = fkc.referenced_object_id
               join sys.columns parent_col on parent_col.object_id = fkc.referenced_object_id and parent_col.column_id = fkc.referenced_column_id
              where fk.parent_object_id = OBJECT_ID(:tablename)
              order by fk.name, fkc.constraint_column_id",
            ['tablename' => $tableName]
        );
    }

    #[Override]
    protected function parseColumnMetadata(array $metadata): array
    {
        $return = [];

        foreach ($metadata as $key => $value) {
            if (!empty($value['character_maximum_length'])) {
                $dataType = strtolower($value['data_type']) . '(' . $value['character_maximum_length'] . ')';
            } else {
                $dataType = strtolower($value['data_type']) . '(' . $value["numeric_precision"] . ',' . $value['numeric_precision_radix'] . ')';
            }

            $return[strtolower($value['column_name'])] = $this->newColumnMetadata(
                    name: (string)$value['column_name'],
                    dbType: strtolower($value['data_type']),
                    required: $value['is_nullable'] == 'NO',
                    default: $value['column_default'],
                    primaryKey: $value['is_primary_key'] == 1,
                    autoIncrement: $value['is_auto_increment'] == 1,
                    generated: $value['is_generated'] == 1,
                    declaredType: $dataType
                );
        }

        return $return;
    }

    #[Override]
    public function getIsolationLevelCommand(?IsolationLevelEnum $isolationLevel = null): string
    {
        return match ($isolationLevel) {
            IsolationLevelEnum::ReadUncommitted => "SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED",
            IsolationLevelEnum::ReadCommitted => "SET TRANSACTION ISOLATION LEVEL READ COMMITTED",
            IsolationLevelEnum::RepeatableRead => "SET TRANSACTION ISOLATION LEVEL REPEATABLE READ",
            IsolationLevelEnum::Serializable => "SET TRANSACTION ISOLATION LEVEL SERIALIZABLE",
            default => "",
        };
    }
}
