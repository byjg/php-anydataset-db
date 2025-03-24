<?php

namespace ByJG\AnyDataset\Db;

interface DbFunctionsInterface
{

    /**
     * Given two or more string the system will return the string containing de proper SQL commands
     * to concatenate these string;
     *
     * use:
     *      for ($i = 0, $numArgs = func_num_args(); $i < $numArgs ; $i++)
     * to get all parameters received.
     *
     * @param string $str1
     * @param string|null $str2
     * @return string
     */
    public function concat(string $str1, ?string $str2 = null): string;

    /**
     * Given a SQL returns it with the proper LIMIT or equivalent method included
     * @param string $sql
     * @param int $start
     * @param int $qty
     * @return string
     */
    public function limit(string $sql, int $start, int $qty = 50): string;

    /**
     * Given a SQL returns it with the proper TOP or equivalent method included
     * @param string $sql
     * @param int $qty
     * @return string
     */
    public function top(string $sql, int $qty): string;

    /**
     * Return if the database provider have a top or similar function
     * @return bool
     */
    public function hasTop(): bool;

    /**
     * Return if the database provider have a limit function
     * @return bool
     */
    public function hasLimit(): bool;

    /**
     * Format date column in sql string given an input format that understands Y M D
     *
     * @param string $format
     * @param string|null $column
     * @return string
     * @example $db->getDbFunctions()->SQLDate("d/m/Y H:i", "dtcriacao")
     */
    public function sqlDate(string $format, ?string $column = null): string;

    /**
     * Format a string date to a string database readable format.
     *
     * @param string $date
     * @param string $dateFormat
     * @return string
     */
    public function toDate(string $date, string $dateFormat): string;

    /**
     * Format a string database readable format to a string date in a free format.
     *
     * @param string $date
     * @param string $dateFormat
     * @return string
     */
    public function fromDate(string $date, string $dateFormat): string;

    /**
     *
     * @param DbDriverInterface $dbDriver
     * @param string|SqlStatement $sql
     * @param array|null $param
     * @return mixed
     */
    public function executeAndGetInsertedId(DbDriverInterface $dbDriver, string|SqlStatement $sql, ?array $param = null): mixed;

    /**
     * @param string|array $field
     * @return string|array
     */
    public function delimiterField(string|array $field): string|array;

    /**
     * @param string|array $table
     * @return string
     */
    public function delimiterTable(string|array $table): string;

    public function forUpdate(string $sql): string;

    public function hasForUpdate(): bool;

    public function getTableMetadata(DbDriverInterface $dbdataset, string $tableName): array;

    public function getIsolationLevelCommand(?IsolationLevelEnum $isolationLevel = null): string;

    public function getJoinTablesUpdate(array $tables): array;
}
