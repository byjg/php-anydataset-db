<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\SqlDialect\DuckdbDialect;
use ByJG\Util\Uri;
use Override;

/**
 * DuckDB, embedded like SQLite and meant for analytics. Needs the pdo_duckdb extension.
 *
 * duckdb:///path/to/file.duckdb opens (or creates) a file, duckdb:// an in-memory database.
 */
class PdoDuckdb extends DbPdoDriver
{

    #[Override]
    public static function schema(): array
    {
        return ['duckdb'];
    }

    #[Override]
    public function getSqlDialectClass(): string
    {
        return DuckdbDialect::class;
    }

    /**
     * PdoDuckdb constructor.
     *
     * @param Uri $connUri
     * @throws DbDriverNotConnected
     * @throws NotAvailableException
     */
    public function __construct(Uri $connUri)
    {
        parent::__construct($connUri, [], []);
    }
}
