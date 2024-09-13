<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\Util\Uri;

class PdoSqlite extends DbPdoDriver
{

    public static function schema(): array
    {
        return ['sqlite'];
    }

    /**
     * PdoSqlite constructor.
     *
     * @param Uri $connUri
     * @throws DbDriverNotConnected
     */
    public function __construct(Uri $connUri)
    {
        parent::__construct($connUri, [], []);
    }
}
