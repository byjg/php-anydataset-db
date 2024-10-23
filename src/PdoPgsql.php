<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\Util\Uri;

class PdoPgsql extends DbPdoDriver
{
    public static function schema(): array
    {
        return ['pgsql', 'postgres', 'postgresql'];
    }

    /**
     * PdoPgsql constructor.
     *
     * @param Uri $connUri
     * @throws DbDriverNotConnected
     */
    public function __construct(Uri $connUri)
    {
        parent::__construct($connUri, [], []);
    }
}
