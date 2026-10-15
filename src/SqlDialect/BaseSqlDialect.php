<?php

namespace ByJG\AnyDataset\Db\SqlDialect;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\Interfaces\SqlDialectInterface;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\AnyDataset\Db\Metadata\ColumnMetadata;
use ByJG\AnyDataset\Db\Metadata\ForeignKeyMetadata;
use ByJG\AnyDataset\Db\Metadata\IndexMetadata;
use ByJG\AnyDataset\Db\SqlStatement;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use Exception;
use Override;
use Psr\SimpleCache\InvalidArgumentException;

abstract class BaseSqlDialect implements SqlDialectInterface
{

    const DMY = "d-m-Y";
    const MDY = "m-d-Y";
    const YMD = "Y-m-d";
    const DMYH = "d-m-Y H:i:s";
    const MDYH = "m-d-Y H:i:s";
    const YMDH = "Y-m-d H:i:s";

    /**
     * Given two or more string the system will return the string containing the proper
     * SQL commands to concatenate these string;
     * use:
     *     for ($i = 0, $numArgs = func_num_args(); $i < $numArgs ; $i++)
     * to get all parameters received.
     *
     * @param string $str1
     * @param string|null $str2
     * @return string
     */
    #[Override]
    abstract public function concat(string $str1, ?string $str2 = null): string;

    /**
     * Given a SQL returns it with the proper LIMIT or equivalent method included
     *
     * @param string $sql
     * @param int $start
     * @param int $qty
     * @return string
     */
    #[Override]
    abstract public function limit(string $sql, int $start, int $qty = 50): string;

    /**
     * Given a SQL returns it with the proper TOP or equivalent method included
     *
     * @param string $sql
     * @param int $qty
     * @return string
     */
    #[Override]
    abstract public function top(string $sql, int $qty): string;

    /**
     * Return if the database provider have a top or similar function
     *
     * @return bool
     */
    #[Override]
    public function hasTop(): bool
    {
        return false;
    }

    /**
     * Return if the database provider have a limit function
     *
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
     * @param string $format
     * @param string|null $column
     * @return string
     * @example $db->getDbFunctions()->SQLDate("d/m/Y H:i", "dtcriacao")
     */
    #[Override]
    abstract public function sqlDate(string $format, ?string $column = null): string;


    protected function prepareSqlDate(string $input, array $pattern, string $delimitString = "'"): array
    {
        $split = preg_split('/([YyMmQqDdhHisaA])/', $input, -1, PREG_SPLIT_DELIM_CAPTURE);
        $prepareString = array_filter(
            $split !== false ? $split : [],
            fn($value) => $value !== ''
        );

        return array_map(
            fn($value) => $pattern[$value] ?? $delimitString . $value . $delimitString,
            $prepareString
        );
    }

    /**
     * @param DatabaseExecutor $executor
     * @param string|SqlStatement $sql
     * @param array|null $param
     * @return mixed
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     * @throws InvalidArgumentException
     */
    #[Override]
    public function executeAndGetInsertedId(DatabaseExecutor $executor, string|SqlStatement $sql, ?array $param = null): mixed
    {
        $executor->execute($sql, $param);

        return $executor->getScalar($this->getSqlLastInsertId());
    }

    #[Override]
    public function getSqlLastInsertId(): string
    {
        return "select null id";
    }

    protected string $deliFieldLeft = '';
    protected string $deliFieldRight = '';
    protected string $deliTableLeft = '';
    protected string $deliTableRight = '';

    /**
     * @param string|array $field
     * @return string|array
     */
    #[Override]
    public function delimiterField(string|array $field): string|array
    {
        $delimiter = fn($fld): string => $this->deliFieldLeft .
            implode($this->deliFieldRight . '.' . $this->deliFieldLeft, explode('.', $fld)) .
            $this->deliFieldRight;

        $result = array_map($delimiter, (array)$field);

        return is_string($field) ? $result[0] : $result;
    }

    #[Override]
    public function delimiterTable(string|array $table): string
    {
        $parts = is_array($table) ? $table : explode('.', $table);
        return implode('.', array_map(
            fn($part) => $this->deliTableLeft . $part . $this->deliTableRight,
            $parts
        ));
    }

    #[Override]
    public function forUpdate(string $sql): string
    {
        $pattern = '#\bfor\s+update\b#i';
        return preg_match($pattern, $sql) ? $sql : "$sql FOR UPDATE ";
    }

    #[Override]
    abstract public function hasForUpdate(): bool;

    /**
     * @throws Exception
     */
    #[Override]
    public function getTableMetadata(DatabaseExecutor $executor, string $tableName): array
    {
        throw new Exception("Not implemented");
    }

    /**
     * @return array<string, ColumnMetadata>
     * @throws XmlUtilException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     */
    protected function getTableMetadataFromSql(DatabaseExecutor $executor, string $sql): array
    {
        $metadata = $executor->getIterator($sql)->toArray();
        return $this->parseColumnMetadata($metadata);
    }

    /**
     * Parse column metadata from database-specific format into standardized format
     *
     * @param array<array-key, array<string, mixed>> $metadata Raw metadata from database
     * @return array<string, ColumnMetadata>
     */
    abstract protected function parseColumnMetadata(array $metadata): array;

    /**
     * @param string|null $declaredType The type parsed for phpType, length and precision, when it is not $dbType
     */
    protected function newColumnMetadata(
        string $name,
        string $dbType,
        bool $required,
        mixed $default,
        bool $primaryKey,
        bool $autoIncrement,
        bool $generated,
        ?string $declaredType = null
    ): ColumnMetadata {
        $type = $this->parseTypeMetadata($declaredType ?? $dbType);

        return new ColumnMetadata(
            name: $name,
            dbType: $dbType,
            required: $required,
            default: $default,
            phpType: $type['phpType'],
            length: $type['length'],
            precision: $type['precision'],
            primaryKey: $primaryKey,
            autoIncrement: $autoIncrement,
            generated: $generated,
        );
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function getTableIndexes(DatabaseExecutor $executor, string $tableName): array
    {
        throw new Exception("Not implemented");
    }

    /**
     * Runs a query returning one row per index column, in index order, with the
     * fields index_name, column_name and is_unique, and groups it by index.
     *
     * @return list<IndexMetadata>
     * @throws XmlUtilException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     */
    protected function getTableIndexesFromSql(DatabaseExecutor $executor, string $sql, array $params = []): array
    {
        return $this->groupIndexRows($executor->getIterator($sql, $params)->toArray());
    }

    /**
     * @param array<array-key, array<string, mixed>> $rows index_name, column_name and is_unique, in index order
     * @return list<IndexMetadata>
     */
    protected function groupIndexRows(array $rows): array
    {
        // Kept apart from the array keys, which PHP turns into int for a numeric name
        $names = [];
        $columns = [];
        $unique = [];
        foreach ($rows as $row) {
            $name = (string)$row['index_name'];
            $names[$name] = $name;
            $columns[$name][] = (string)$row['column_name'];
            $unique[$name] = (bool)$row['is_unique'];
        }
        ksort($names);

        $indexes = [];
        foreach ($names as $key => $name) {
            $indexes[] = new IndexMetadata($name, $columns[$key], $unique[$key]);
        }
        return $indexes;
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function getTableForeignKeys(DatabaseExecutor $executor, string $tableName): array
    {
        throw new Exception("Not implemented");
    }

    /**
     * Runs a query returning one row per foreign key column, in key order, with the fields
     * constraint_name, column_name, parent_table and parent_column, and groups it by key.
     *
     * @return list<ForeignKeyMetadata>
     * @throws XmlUtilException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     */
    protected function getTableForeignKeysFromSql(DatabaseExecutor $executor, string $sql, array $params = []): array
    {
        return $this->groupForeignKeyRows($executor->getIterator($sql, $params)->toArray());
    }

    /**
     * @param array<array-key, array<string, mixed>> $rows constraint_name, column_name, parent_table and parent_column, in key order
     * @return list<ForeignKeyMetadata>
     */
    protected function groupForeignKeyRows(array $rows): array
    {
        $columns = [];
        $parentTable = [];
        $parentColumns = [];
        foreach ($rows as $row) {
            $name = (string)$row['constraint_name'];
            $columns[$name][] = (string)$row['column_name'];
            $parentTable[$name] = (string)$row['parent_table'];
            $parentColumns[$name][] = (string)$row['parent_column'];
        }
        ksort($columns);

        $foreignKeys = [];
        foreach ($columns as $name => $keyColumns) {
            $foreignKeys[] = new ForeignKeyMetadata($keyColumns, $parentTable[$name], $parentColumns[$name]);
        }
        return $foreignKeys;
    }

    protected function parseTypeMetadata(string $type): array
    {
        $defaultResult = ['phpType' => 'string', 'length' => null, 'precision' => null];

        if (!preg_match('/(?<type>[a-z0-9\s]+)(\((?<len>\d+)(,(?<precision>\d+))?\))?/i', $type, $matches)) {
            return $defaultResult;
        }

        $type = strtolower($matches['type']);
        $length = isset($matches['len']) ? (int)$matches['len'] : null;
        $precision = isset($matches['precision']) ? (int)$matches['precision'] : null;

        $typeMap = [
            'int' => ['phpType' => 'integer', 'length' => null, 'precision' => null],
            'char' => ['phpType' => 'string', 'length' => $length, 'precision' => null],
            'text' => ['phpType' => 'string', 'length' => null, 'precision' => null],
            'real' => ['phpType' => 'float', 'length' => $length, 'precision' => $precision],
            'double' => ['phpType' => 'float', 'length' => $length, 'precision' => $precision],
            'float' => ['phpType' => 'float', 'length' => $length, 'precision' => $precision],
            'num' => ['phpType' => 'float', 'length' => $length, 'precision' => $precision],
            'dec' => ['phpType' => 'float', 'length' => $length, 'precision' => $precision],
            'bool' => ['phpType' => 'bool', 'length' => null, 'precision' => null],
        ];

        foreach ($typeMap as $key => $result) {
            if (str_contains($type, $key)) {
                return $result;
            }
        }

        return $defaultResult;
    }

    #[Override]
    public function getIsolationLevelCommand(?IsolationLevelEnum $isolationLevel = null): string
    {
        return "";
    }

    #[Override]
    public function getPingSql(): string
    {
        return "SELECT 1";
    }

    #[Override]
    public function getJoinTablesUpdate(array $tables): array
    {
        return [
            'sql' => '',
            'position' => 'before_set'
        ];
    }
}
