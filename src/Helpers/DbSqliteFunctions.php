<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use Override;

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

    /**
     *
     * @param DbDriverInterface $dbdataset
     * @param string $sql
     * @param array|null $param
     * @return mixed
     */
    #[Override]
    public function executeAndGetInsertedId(DbDriverInterface $dbdataset, string $sql, ?array $param = null): mixed
    {
        parent::executeAndGetInsertedId($dbdataset, $sql, $param);
        return $dbdataset->getScalar("SELECT last_insert_rowid()");
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
    public function getTableMetadata(DbDriverInterface $dbdataset, string $tableName): array
    {
        $sql = "PRAGMA table_info(" . $this->deliTableLeft . $tableName . $this->deliTableRight . ")";
        return $this->getTableMetadataFromSql($dbdataset, $sql);
    }

    #[Override]
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

    #[Override]
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
