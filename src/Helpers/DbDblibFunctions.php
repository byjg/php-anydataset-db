<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use Override;

class DbDblibFunctions extends DbBaseFunctions
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
    public function getTableMetadata(DbDriverInterface $dbdataset, string $tableName): array
    {
        $sql = "select * from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME = '$tableName'";
        return $this->getTableMetadataFromSql($dbdataset, $sql);
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

            $return[strtolower($value['column_name'])] = [
                    'name' => $value['column_name'],
                    'dbType' => strtolower($value['data_type']),
                    'required' => $value['is_nullable'] == 'NO',
                    'default' => $value['column_default'],
                ] + $this->parseTypeMetadata($dataType);
        }

        return $return;
    }

    #[Override]
    public function getIsolationLevelCommand(?IsolationLevelEnum $isolationLevel = null): string
    {
        return match ($isolationLevel) {
            IsolationLevelEnum::READ_UNCOMMITTED => "SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED",
            IsolationLevelEnum::READ_COMMITTED => "SET TRANSACTION ISOLATION LEVEL READ COMMITTED",
            IsolationLevelEnum::REPEATABLE_READ => "SET TRANSACTION ISOLATION LEVEL REPEATABLE READ",
            IsolationLevelEnum::SERIALIZABLE => "SET TRANSACTION ISOLATION LEVEL SERIALIZABLE",
            default => "",
        };
    }
}
