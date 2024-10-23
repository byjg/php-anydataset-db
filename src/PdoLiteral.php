<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
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
     * @param array|null $preOptions
     * @param array|null $postOptions
     * @param array $executeAfterConnect
     * @throws DbDriverNotConnected
     */
    public function __construct(string $pdoConnStr, string $username = "", string $password = "", ?array $preOptions = null, ?array $postOptions = null, array $executeAfterConnect = [])
    {
        $parts = explode(":", $pdoConnStr, 2);

        $credential = "";
        if (!empty($username)) {
            $credential = "$username:$password@";
        }

        parent::__construct(new Uri("literal://{$credential}{$parts[0]}?connection=" . urlencode($parts[1])), $preOptions, $postOptions, $executeAfterConnect);
    }
}
