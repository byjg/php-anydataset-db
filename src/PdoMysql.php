<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\Util\Uri;
use Override;
use PDO;

class PdoMysql extends DbPdoDriver
{

    #[Override]
    public static function schema(): array
    {
        return ['mysql', 'mariadb'];
    }

    protected array $mysqlAttr = [
        "ca" => PDO::MYSQL_ATTR_SSL_CA,
        "capath" => PDO::MYSQL_ATTR_SSL_CAPATH,
        "cert" => PDO::MYSQL_ATTR_SSL_CERT,
        "key" => PDO::MYSQL_ATTR_SSL_KEY,
        "cipher" => PDO::MYSQL_ATTR_SSL_CIPHER,
        "verifyssl" => 1014 // PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT (>=7.1)
    ];

    /**
     * PdoMysql constructor.
     *
     * @param Uri $connUri
     * @throws DbDriverNotConnected
     */
    public function __construct(Uri $connUri)
    {
        $preOptions = [];

        $postOptions = [
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ];

        $executeAfterConnect = [
            "SET NAMES utf8"
        ];

        if (!empty($connUri->getQuery())) {
            foreach ($this->mysqlAttr as $key => $property) {
                $value = $connUri->getQueryPart($key);
                if (!empty($value)) {
                    $prepValue = urldecode($value);
                    if ($prepValue === 'false') {
                        $prepValue = false;
                    } else if ($prepValue === 'true') {
                        $prepValue = true;
                    }
                    $preOptions[$property] = $prepValue;
                }
            }
        }

        $this->setSupportMultiRowset(true);

        parent::__construct($connUri, $preOptions, $postOptions, $executeAfterConnect);
    }
}
