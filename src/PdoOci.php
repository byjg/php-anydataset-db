<?php

namespace ByJG\AnyDataset\Db;

use ByJG\Util\Uri;
use PDO;

class PdoOci extends DbPdoDriver
{

    public function __construct(Uri $connUri)
    {
        $this->connectionUri = $connUri;
        $strconn = $connUri->getScheme(). ":dbname=" . DbOci8Driver::getTnsString($connUri);

        // Create Connection
        $this->instance = new PDO(
            $strconn,
            $this->connectionUri->getUsername(),
            $this->connectionUri->getPassword()
        );
    }
}
