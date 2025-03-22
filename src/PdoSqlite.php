<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\Util\Uri;
use Override;

class PdoSqlite extends DbPdoDriver
{

    #[Override]
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
