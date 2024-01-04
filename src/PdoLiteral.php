<?php

namespace ByJG\AnyDataset\Db;

use ByJG\Util\Uri;

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

        parent::__construct(new Uri("{$parts[0]}://{$credential}pdo?connection=" . urlencode($pdoConnStr)), $preOptions, $postOptions);
    }

    public function createPdoConnStr(Uri $connUri)
    {
        return $this->connectionUri->getQueryPart("connection");
    }
}
