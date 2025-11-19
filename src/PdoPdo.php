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

class PdoPdo extends DbPdoDriver
{

    #[Override]
    public static function schema(): array
    {
        return ['pdo'];
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
     * PdoPdo constructor.
     *
     * @param Uri $connUri
     * @param array|null $preOptions
     * @param array|null $postOptions
     * @param array $executeAfterConnect
     * @throws DbDriverNotConnected
     */
    public function __construct(Uri $connUri, ?array $preOptions = [], ?array $postOptions = [], array $executeAfterConnect = [])
    {
        parent::__construct($connUri, $preOptions, $postOptions, $executeAfterConnect);
    }
}
