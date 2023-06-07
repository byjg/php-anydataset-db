<?php

namespace ByJG\AnyDataset\Db;

use ByJG\Util\Uri;
use PDO;

class PdoLiteral extends DbPdoDriver
{

    public static function schema()
    {
        return null;
    }

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

        $credential = "";
        if (!empty($username)) {
            $credential = "$username:$password@";
        }

        $this->connectionUri = new Uri( "{$parts[0]}://{$credential}pdo?connection=" . urlencode($pdoConnStr));
        $this->preOptions = $preOptions;
        $this->postOptions = $postOptions;
        $this->validateConnUri();
        $this->reconnect();
    }

    public function createPdoConnStr(Uri $connUri)
    {
        return $this->connectionUri->getQueryPart("connection");
    }
}
