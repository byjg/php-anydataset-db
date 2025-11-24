<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\SqlDialect\GenericPdoDialect;
use ByJG\Util\Uri;
use Override;

class PdoOdbc extends DbPdoDriver
{

    #[Override]
    public static function schema(): array
    {
        return ['odbc'];
    }

    #[Override]
    public function getSqlDialectClass(): string
    {
        return GenericPdoDialect::class;
    }

    /**
     * PdoOdbc constructor.
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
