<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\SqlDialect\MysqlSqlDialect;
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

    #[Override]
    public function getSqlDialectClass(): string
    {
        return MysqlSqlDialect::class;
    }

    /**
     * PdoMysql constructor.
     *
     * @param Uri $connUri
     * @throws DbDriverNotConnected
     * @throws NotAvailableException
     * @psalm-suppress InvalidClass
     */
    public function __construct(Uri $connUri)
    {
        $preOptions = [];

        $postOptions = [
            class_exists(Pdo\Mysql::class) ? Pdo\Mysql::ATTR_USE_BUFFERED_QUERY : PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ];

        $executeAfterConnect = [
            "SET NAMES utf8"
        ];

        $validAttributes = [
            "ca" => class_exists(Pdo\Mysql::class) ? Pdo\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA,
            "capath" => class_exists(Pdo\Mysql::class) ? Pdo\Mysql::ATTR_SSL_CAPATH : PDO::MYSQL_ATTR_SSL_CAPATH,
            "cert" => class_exists(Pdo\Mysql::class) ? Pdo\Mysql::ATTR_SSL_CERT : PDO::MYSQL_ATTR_SSL_CERT,
            "key" => class_exists(Pdo\Mysql::class) ? Pdo\Mysql::ATTR_SSL_KEY : PDO::MYSQL_ATTR_SSL_KEY,
            "cipher" => class_exists(Pdo\Mysql::class) ? Pdo\Mysql::ATTR_SSL_CIPHER : PDO::MYSQL_ATTR_SSL_CIPHER,
            "verifyssl" => class_exists(Pdo\Mysql::class) ? Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT : PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT
        ];

        if (!empty($connUri->getQuery())) {
            foreach ($validAttributes as $key => $property) {
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
