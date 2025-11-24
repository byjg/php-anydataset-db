<?php

namespace ByJG\AnyDataset\Db\SqlDialect;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\AnyDataset\Db\SqlStatement;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use Override;
use Psr\SimpleCache\InvalidArgumentException;

class OciDialect extends BaseSqlDialect
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
        if (stripos($sql, ' OFFSET ') === false && stripos($sql, ' FETCH NEXT ') === false) {
            return $sql . " OFFSET $start ROWS FETCH NEXT $qty ROWS ONLY";
        }

        $result = preg_replace(
            '~(\s[Oo][Ff][Ff][Ss][Ee][Tt])\s.*?\s([Rr][Oo][Ww][Ss])\s.*?\s([Ff][Ee][Tt][Cc][Hh]\s[Nn][Ee][Xx][Tt])\s.*~',
            '$1 ' . $start . ' $2 ' . '$3 ' . $qty . ' ROWS ONLY',
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
        $sqlString = $sql instanceof SqlStatement ? $sql->getSql() : $sql;
        preg_match('/INSERT INTO ([a-zA-Z0-9_]+)/i', $sqlString, $matches);
        $tableName = $matches[1] ?? null;

        if (!empty($tableName)) {
            $tableName = strtoupper($tableName);

            // Get the primary key of the table
            $primaryKeyResult = $executor->getScalar("SELECT cols.column_name
                FROM all_constraints cons, all_cons_columns cols
                WHERE cols.table_name = '$tableName'
                AND cons.constraint_type = 'P'
                AND cons.constraint_name = cols.constraint_name
                AND cons.owner = cols.owner
                AND ROWNUM = 1");

            // Get the default value of the primary key
            $defaultValueResult = $executor->getScalar("SELECT DATA_DEFAULT
                FROM USER_TAB_COLUMNS
                WHERE TABLE_NAME = '$tableName'
                AND COLUMN_NAME = '$primaryKeyResult'");
        }

        $executor->execute($sql, $param);

        if (!empty($tableName) && !empty($defaultValueResult)) {

            // Check if the default value is a sequence's nextval
            if (str_contains($defaultValueResult, '.nextval')) {
                // Extract the sequence name
                $sequenceName = str_replace('.nextval', '', $defaultValueResult);

                // Return the CURRVAL of the sequence
                return $executor->getScalar("SELECT $sequenceName.currval FROM DUAL");
            }
        }

        return null;

    }

    #[Override]
    public function hasForUpdate(): bool
    {
        return true;
    }

    #[Override]
    public function getTableMetadata(DatabaseExecutor $executor, string $tableName): array
    {
        $tableName = strtoupper($tableName);
        $sql = "SELECT
                    COLUMN_NAME,
                    DATA_TYPE ||
                        CASE
                            WHEN COALESCE(DATA_PRECISION, CHAR_LENGTH, 0) <> 0
                                THEN '(' || COALESCE(DATA_PRECISION, CHAR_LENGTH) || (
                                    CASE WHEN COALESCE(DATA_SCALE, 0) <> 0 THEN ',' || DATA_SCALE END
                                ) || ')'
                        END AS TYPE,
                    DATA_DEFAULT AS COLUMN_DEFAULT,
                    NULLABLE
                FROM ALL_TAB_COLUMNS WHERE TABLE_NAME = '$tableName'";

        return $this->getTableMetadataFromSql($executor, $sql);
    }

    #[Override]
    protected function parseColumnMetadata(array $metadata): array
    {
        $return = [];

        foreach ($metadata as $key => $value) {
            $return[strtolower($value['column_name'])] = [
                    'name' => $value['column_name'],
                    'dbType' => strtolower($value['type']),
                    'required' => $value['nullable'] == 'N',
                    'default' => $value['column_default'] ?? null,
                ] + $this->parseTypeMetadata(strtolower($value['type']));
        }

        return $return;
    }

    #[Override]
    public function getIsolationLevelCommand(?IsolationLevelEnum $isolationLevel = null): string
    {
        return match ($isolationLevel) {
            IsolationLevelEnum::READ_UNCOMMITTED => "SET TRANSACTION READ WRITE",
            IsolationLevelEnum::READ_COMMITTED, IsolationLevelEnum::REPEATABLE_READ => "SET TRANSACTION ISOLATION LEVEL READ COMMITTED",
            IsolationLevelEnum::SERIALIZABLE => "SET TRANSACTION ISOLATION LEVEL SERIALIZABLE",
            default => "",
        };
    }
}
