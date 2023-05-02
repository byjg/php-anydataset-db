<?php

namespace ByJG\AnyDataset\Db;

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
     * @throws \ByJG\AnyDataset\Core\Exception\NotAvailableException
     */
    public function __construct(Uri $connUri)
    {
        parent::__construct($connUri, [], []);
    }
}
