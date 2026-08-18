<?php

namespace ByJG\AnyDataset\Db\SqlDialect;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\DbD1Driver;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\AnyDataset\Db\SqlStatement;
use Override;

/**
 * Cloudflare D1 speaks SQLite, so almost everything is inherited from SqliteDialect. Only the two
 * behaviours that depend on a persistent connection need to change.
 */
class D1Dialect extends SqliteDialect
{
    /**
     * D1 exposes no interactive transaction, and SQLite's "PRAGMA read_uncommitted" is scoped to a
     * connection, which does not survive between two stateless HTTP requests.
     */
    #[Override]
    public function getIsolationLevelCommand(?IsolationLevelEnum $isolationLevel = null): string
    {
        return "";
    }

    /**
     * "SELECT last_insert_rowid()" would be issued as a separate HTTP request, i.e. on a different
     * connection, and would always answer 0. D1 reports the generated rowid in the response
     * metadata instead, so read it from there.
     */
    #[Override]
    public function executeAndGetInsertedId(
        DatabaseExecutor $executor,
        string|SqlStatement $sql,
        ?array $param = null
    ): mixed {
        $executor->execute($sql, $param);

        $driver = $executor->getDriver();
        if ($driver instanceof DbD1Driver) {
            return $driver->getLastRowId();
        }

        // Not a D1 driver: fall back to the generic behaviour without executing the statement twice.
        return $executor->getScalar($this->getSqlLastInsertId());
    }
}
