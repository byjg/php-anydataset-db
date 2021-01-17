<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Core\Exception\NotAvailableException;

class DbSqlsrvFunctions extends DbDblibFunctions
{

    /**
     * DbSqlsrvFunctions constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}
