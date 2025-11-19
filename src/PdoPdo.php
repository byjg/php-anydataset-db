<?php

namespace ByJG\AnyDataset\Db;

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
