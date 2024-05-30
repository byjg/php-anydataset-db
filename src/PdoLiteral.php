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
        $parts = explode(":", $pdoConnStr, 2);

        $credential = "";
        if (!empty($username)) {
            $credential = "$username:$password@";
        }

        parent::__construct(new Uri("literal://{$credential}{$parts[0]}?connection=" . urlencode($parts[1]), $preOptions, $postOptions));
    }
}
