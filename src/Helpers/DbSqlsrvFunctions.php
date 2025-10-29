<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\SqlStatement;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use Override;
use Psr\SimpleCache\InvalidArgumentException;

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
        // For SQLSRV, we use a more efficient method to get the inserted ID
        $executor->execute($sql, $param);
        return $executor->getScalar("select SCOPE_IDENTITY() as id");
    }
}
