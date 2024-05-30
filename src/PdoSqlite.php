<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\Util\Uri;

class PdoSqlite extends DbPdoDriver
{

    public static function schema()
    {
        return ['sqlite'];
    }

    /**
     * PdoSqlite constructor.
     *
     * @param \ByJG\Util\Uri $connUri
     * @throws NotAvailableException
     */
    public function __construct(Uri $connUri)
    {
        parent::__construct($connUri, [], []);
    }
}
