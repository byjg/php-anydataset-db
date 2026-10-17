<?php

namespace ByJG\AnyDataset\Db\SqlDialect;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use Override;

class SqliteDialect extends BaseSqlDialect
{

    public function __construct()
    {
        $this->deliFieldLeft = '`';
        $this->deliFieldRight = '`';
        $this->deliTableLeft = '`';
        $this->deliTableRight = '`';
    }

    /**
     * @param string $str1
     * @param string|null $str2
     * @return string
     */
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
            $column = "'now'";
        }

        $pattern = [
            'Y' => "%Y",
            'y' => "%Y",
            'M' => "%m",
            'm' => "%m",
            'Q' => "",
            'q' => "",
            'D' => "%d",
            'd' => "%d",
            'h' => "%H",
            'H' => "%H",
            'i' => "%M",
            's' => "%S",
            'a' => "",
            'A' => "",
        ];

        $preparedSql = $this->prepareSqlDate($format, $pattern, '');

        return sprintf(
            "strftime('%s', %s)",
            implode('', $preparedSql),
            $column
        );
    }

    #[Override]
    public function getSqlLastInsertId(): string
    {
        return "select last_insert_rowid() id";
    }

    /**
     * @param string $sql
     * @return string
     * @throws NotAvailableException
     */
    #[Override]
    public function forUpdate(string $sql): string
    {
        throw new NotAvailableException('FOR UPDATE not available for SQLite');
    }

    #[Override]
    public function hasForUpdate(): bool
    {
        return false;
    }

    #[Override]
    public function getTableMetadata(DatabaseExecutor $executor, string $tableName): array
    {
        // table_xinfo, unlike table_info, also lists the generated columns
        $sql = "PRAGMA table_xinfo(" . $this->deliTableLeft . $tableName . $this->deliTableRight . ")";
        return $this->getTableMetadataFromSql($executor, $sql);
    }

    #[Override]
    protected function parseColumnMetadata(array $metadata): array
    {
        $return = [];

        // hidden: 0 a column, 1 a hidden column of a virtual table, 2 or 3 a generated column
        $metadata = array_filter($metadata, fn ($value) => $value['hidden'] != 1);

        // A single-column INTEGER PRIMARY KEY is an alias of the rowid, which SQLite fills in.
        $primaryKeyCount = count(array_filter($metadata, fn ($value) => $value['pk'] > 0));

        foreach ($metadata as $key => $value) {
            $return[strtolower($value['name'])] = $this->newColumnMetadata(
                name: (string)$value['name'],
                dbType: strtolower($value['type']),
                required: $value['notnull'] == 1,
                default: $value['dflt_value'],
                primaryKey: $value['pk'] > 0,
                autoIncrement: $value['pk'] > 0 && $primaryKeyCount == 1 && strtolower($value['type']) == 'integer',
                generated: $value['hidden'] == 2 || $value['hidden'] == 3
            );
        }

        return $return;
    }

    #[Override]
    public function getTableIndexes(DatabaseExecutor $executor, string $tableName): array
    {
        $rows = [];
        $indexList = $executor->getIterator("PRAGMA index_list(" . $this->deliTableLeft . $tableName . $this->deliTableRight . ")")->toArray();
        foreach ($indexList as $index) {
            if ($index['origin'] == 'pk') {
                continue;
            }
            $columns = $executor->getIterator("PRAGMA index_info(" . $this->deliTableLeft . $index['name'] . $this->deliTableRight . ")")->toArray();
            usort($columns, fn ($a, $b) => $a['seqno'] <=> $b['seqno']);
            foreach ($columns as $column) {
                $rows[] = ['index_name' => $index['name'], 'column_name' => $column['name'], 'is_unique' => $index['unique']];
            }
        }

        return $this->groupIndexRows($rows);
    }

    #[Override]
    public function getTableForeignKeys(DatabaseExecutor $executor, string $tableName): array
    {
        $foreignKeys = $executor->getIterator("PRAGMA foreign_key_list(" . $this->deliTableLeft . $tableName . $this->deliTableRight . ")")->toArray();
        usort($foreignKeys, fn ($a, $b) => [$a['id'], $a['seq']] <=> [$b['id'], $b['seq']]);

        $rows = [];
        foreach ($foreignKeys as $foreignKey) {
            $parentColumn = $foreignKey['to'];
            if ($parentColumn === null) {
                // "REFERENCES parent" without columns points to the parent's primary key
                $parentColumn = $this->getPrimaryKeyColumns($executor, $foreignKey['table'])[$foreignKey['seq']];
            }
            $rows[] = [
                'constraint_name' => $foreignKey['id'],
                'column_name' => $foreignKey['from'],
                'parent_table' => $foreignKey['table'],
                'parent_column' => $parentColumn,
            ];
        }

        return $this->groupForeignKeyRows($rows);
    }

    /**
     * @return list<string> the primary key columns, in key order
     */
    protected function getPrimaryKeyColumns(DatabaseExecutor $executor, string $tableName): array
    {
        $columns = array_filter(
            $executor->getIterator("PRAGMA table_info(" . $this->deliTableLeft . $tableName . $this->deliTableRight . ")")->toArray(),
            fn ($column) => $column['pk'] > 0
        );
        usort($columns, fn ($a, $b) => $a['pk'] <=> $b['pk']);

        return array_column($columns, 'name');
    }

    #[Override]
    public function getIsolationLevelCommand(?IsolationLevelEnum $isolationLevel = null): string
    {
        return match ($isolationLevel) {
            IsolationLevelEnum::ReadUncommitted => "PRAGMA read_uncommitted = true;",
            default => "",
        };
    }
    
}
