<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\AnyDataset\Db\DbDriverInterface;

class DbPdoFunctions extends DbBaseFunctions
{

    public function __construct()
    {
        $this->deliFieldLeft = '';
        $this->deliFieldRight = '';
        $this->deliTableLeft = '';
        $this->deliTableRight = '';
    }

    public function concat($str1, $str2 = null)
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
    public function limit($sql, $start, $qty = null)
    {
        return null;
    }

    /**
     * Given a SQL returns it with the proper TOP or equivalent method included
     * @param string $sql
     * @param int $qty
     * @return string
     */
    public function top($sql, $qty)
    {
        return null;
    }

    /**
     * Return if the database provider have a top or similar function
     * @return bool
     */
    public function hasTop()
    {
        return false;
    }

    /**
     * Return if the database provider have a limit function
     * @return bool
     */
    public function hasLimit()
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
    public function sqlDate($format, $column = null)
    {
        return null;
    }

    public function hasForUpdate()
    {
        return false;
    }
}
