<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\SqlDialect\DblibDialect;
use ByJG\AnyDataset\Db\SqlDialect\GenericPdoDialect;
use ByJG\AnyDataset\Db\SqlDialect\MysqlDialect;
use ByJG\AnyDataset\Db\SqlDialect\OciDialect;
use ByJG\AnyDataset\Db\SqlDialect\PgsqlDialect;
use ByJG\AnyDataset\Db\SqlDialect\SqliteDialect;
use ByJG\AnyDataset\Db\SqlDialect\SqlsrvDialect;
use ByJG\Util\Uri;
use Override;
use PDO;

class PdoLiteral extends DbPdoDriver
{

    #[Override]
    public static function schema()
    {
        return null;
    }

    #[Override]
    public function getSqlDialectClass(): string
    {
        // Detect PDO driver at runtime
        $pdo = $this->getDbConnection();
        if ($pdo instanceof PDO) {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            return match ($driver) {
                'mysql' => MysqlDialect::class,
                'sqlite' => SqliteDialect::class,
                'pgsql' => PgsqlDialect::class,
                'sqlsrv' => SqlsrvDialect::class,
                'dblib' => DblibDialect::class,
                'oci' => OciDialect::class,
                default => GenericPdoDialect::class,
            };
        }

        return GenericPdoDialect::class;
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
     * @throws NotAvailableException
     */
    public function __construct(string $pdoConnStr, string $username = "", string $password = "", ?array $preOptions = null, ?array $postOptions = null, array $executeAfterConnect = [])
    {
        $parts = explode(":", $pdoConnStr, 2);

        $credential = "";
        if (!empty($username)) {
            $credential = "$username:$password@";
        }

        parent::__construct(new Uri("literal://{$credential}{$parts[0]}?connection=" . urlencode($parts[1] ?? '')), $preOptions, $postOptions, $executeAfterConnect);
    }
}
