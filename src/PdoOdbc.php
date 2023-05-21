<?php

namespace ByJG\AnyDataset\Db;

use ByJG\Util\Uri;

class PdoOdbc extends DbPdoDriver
{

    public static function schema()
    {
        return ['odbc'];
    }

    /**
     * PdoOdbc constructor.
     *
     * @param \ByJG\Util\Uri $connUri
     * @throws \ByJG\AnyDataset\Core\Exception\NotAvailableException
     */
    public function __construct(Uri $connUri)
    {
        parent::__construct($connUri, [], []);
    }
}
