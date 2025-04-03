<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\Util\Uri;
use Override;

class PdoPgsql extends DbPdoDriver
{
    #[Override]
    public static function schema(): array
    {
        return ['pgsql', 'postgres', 'postgresql'];
    }

    /**
     * PdoPgsql constructor.
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
