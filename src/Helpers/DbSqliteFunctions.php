<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\IsolationLevelEnum;

class DbSqliteFunctions extends DbBaseFunctions
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
        if (stripos($sql, ' LIMIT ') === false) {
            $sql = $sql . " LIMIT x, y";
        }

        return preg_replace(
            '~(\s[Ll][Ii][Mm][Ii][Tt])\s.*?,\s*.*~',
            '$1 ' . $start .', ' .$qty,
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

    public function getSqlLastInsertId(): string
    {
        return "SELECT last_insert_rowid()";
    }

    /**
     * @param string $sql
     * @return string
     * @throws NotAvailableException
     */
    public function forUpdate(string $sql): string
    {
        throw new NotAvailableException('FOR UPDATE not available for SQLite');
    }

    public function hasForUpdate(): bool
    {
        return false;
    }

    public function getTableMetadata(DbDriverInterface $dbdataset, string $tableName): array
    {
        $sql = "PRAGMA table_info(" . $this->deliTableLeft . $tableName . $this->deliTableRight . ")";
        return $this->getTableMetadataFromSql($dbdataset, $sql);
    }

    protected function parseColumnMetadata($metadata)
    {
        $return = [];

        foreach ($metadata as $key => $value) {
            $return[strtolower($value['name'])] = [
                'name' => $value['name'],
                'dbType' => strtolower($value['type']),
                'required' => $value['notnull'] == 1,
                'default' => $value['dflt_value'],
            ] + $this->parseTypeMetadata(strtolower($value['type']));
        }

        return $return;
    }

    public function getIsolationLevelCommand(?IsolationLevelEnum $isolationLevel = null): string
    {
        switch ($isolationLevel) {
            case IsolationLevelEnum::READ_UNCOMMITTED:
                return "PRAGMA read_uncommitted = true;";
            case IsolationLevelEnum::READ_COMMITTED:
            case IsolationLevelEnum::REPEATABLE_READ:
            case IsolationLevelEnum::SERIALIZABLE:
            default:
                return "";
        }
    }
    
}
