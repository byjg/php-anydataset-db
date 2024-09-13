<?php

namespace ByJG\AnyDataset\Db\Helpers;

class DbPdoFunctions extends DbBaseFunctions
{

    public function __construct()
    {
        $this->deliFieldLeft = '';
        $this->deliFieldRight = '';
        $this->deliTableLeft = '';
        $this->deliTableRight = '';
    }

    public function concat(string $str1, ?string $str2 = null): string
    {
        return null;
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
        return null;
    }

    /**
     * Given a SQL returns it with the proper TOP or equivalent method included
     * @param string $sql
     * @param int $qty
     * @return string
     */
    public function top(string $sql, int $qty): string
    {
        return null;
    }

    /**
     * Return if the database provider have a top or similar function
     * @return bool
     */
    public function hasTop(): bool
    {
        return false;
    }

    /**
     * Return if the database provider have a limit function
     * @return bool
     */
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
    public function sqlDate(string $format, ?string $column = null): string
    {
        return null;
    }

    public function hasForUpdate(): bool
    {
        return false;
    }
}
