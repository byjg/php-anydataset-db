<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\Util\Uri;

class PdoOdbc extends DbPdoDriver
{

    public static function schema(): array
    {
        return ['odbc'];
    }

    /**
     * PdoOdbc constructor.
     *
     * @param Uri $connUri
     * @throws DbDriverNotConnected
     */
    public function __construct(Uri $connUri)
    {
        parent::__construct($connUri, [], []);
    }
}
