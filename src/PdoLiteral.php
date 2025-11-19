<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\SqlDialect\DblibSqlDialect;
use ByJG\AnyDataset\Db\SqlDialect\GenericPdoSqlDialect;
use ByJG\AnyDataset\Db\SqlDialect\MysqlSqlDialect;
use ByJG\AnyDataset\Db\SqlDialect\OciSqlDialect;
use ByJG\AnyDataset\Db\SqlDialect\PostgresSqlDialect;
use ByJG\AnyDataset\Db\SqlDialect\SqliteSqlDialect;
use ByJG\AnyDataset\Db\SqlDialect\SqlsrvSqlDialect;
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
                'mysql' => MysqlSqlDialect::class,
                'sqlite' => SqliteSqlDialect::class,
                'pgsql' => PostgresSqlDialect::class,
                'sqlsrv' => SqlsrvSqlDialect::class,
                'dblib' => DblibSqlDialect::class,
                'oci' => OciSqlDialect::class,
                default => GenericPdoSqlDialect::class,
            };
        }

        return GenericPdoSqlDialect::class;
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
