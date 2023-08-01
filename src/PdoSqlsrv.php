<?php

namespace ByJG\AnyDataset\Db;

use ByJG\Util\Uri;

class PdoSqlsrv extends PdoDblib
{

    public static function schema()
    {
        return ['sqlsrv'];
    }

    /**
     * PdoSqlsrv constructor.
     *
     * @param Uri $connUri
     * @throws \ByJG\AnyDataset\Core\Exception\NotAvailableException
     */
    public function __construct($connUri)
    {
        parent::__construct($connUri);
    }
}
