<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\AnyDataset\Db\DbDriverInterface;
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
     * @param DbDriverInterface $dbdataset
     * @param string $sql
     * @param array|null $param
     * @return mixed
     */
    #[Override]
    public function executeAndGetInsertedId(DbDriverInterface $dbdataset, string $sql, ?array $param = null): mixed
    {
        // For SQLSRV, we use a more efficient method to get the inserted ID
        $insertedId = parent::executeAndGetInsertedId($dbdataset, $sql, $param);

        // SQLSRV can directly use SCOPE_IDENTITY() which is faster
        if ($insertedId === null) {
            $iterator = $dbdataset->getIterator("select SCOPE_IDENTITY() as id");
            if ($iterator->hasNext()) {
                $singleRow = $iterator->moveNext();
                $insertedId = $singleRow->get("id");
            }
        }

        return $insertedId;
    }
}
