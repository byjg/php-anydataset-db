<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\SqlStatement;
use Override;

/**
 * DbSqlsrvFunctions class for Microsoft SQL Server using the SQLSRV extension
 */
class DbSqlsrvFunctions extends DbDblibFunctions
{
    /**
     * DbSqlsrvFunctions constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute SQL with optimized handling for SQLSRV
     *
     * @param DbDriverInterface $dbDriver
     * @param string|SqlStatement $sql
     * @param array|null $param
     * @return mixed
     */
    #[Override]
    public function executeAndGetInsertedId(DbDriverInterface $dbDriver, string|SqlStatement $sql, ?array $param = null): mixed
    {
        // For SQLSRV, we use a more efficient method to get the inserted ID
        $dbDriver->execute($sql, $param);
        return $dbDriver->getScalar("select SCOPE_IDENTITY() as id");
    }
}
