<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\Util\Uri;

class PdoPgsql extends DbPdoDriver
{
    public static function schema()
    {
        return ['pgsql', 'postgres', 'postgresql'];
    }

    /**
     * PdoPgsql constructor.
     *
     * @param \ByJG\Util\Uri $connUri
     * @throws NotAvailableException
     */
    public function __construct(Uri $connUri)
    {
        parent::__construct($connUri, [], []);
    }
}
