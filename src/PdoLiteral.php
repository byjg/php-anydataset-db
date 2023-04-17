<?php

namespace ByJG\AnyDataset\Db;

use ByJG\Util\Uri;
use PDO;

class PdoLiteral extends DbPdoDriver
{

    /**
     * PdoLiteral constructor.
     *
     * @param string $pdoConnStr
     * @param string $username
     * @param string $password
     * @param array $preOptions
     * @param array $postOptions
     */
    public function __construct($pdoConnStr, $username = "", $password = "", $preOptions = null, $postOptions = null)
    {
        $parts = explode(":", $pdoConnStr);

        $this->connectionUri = new Uri($parts[0] . "://$username:$password@literal");

        $this->createPdoInstance($pdoConnStr, $preOptions, $postOptions);
    }
}
