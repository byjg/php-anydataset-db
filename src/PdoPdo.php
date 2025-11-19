<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\Helpers\DbDblibFunctions;
use ByJG\AnyDataset\Db\Helpers\DbMysqlFunctions;
use ByJG\AnyDataset\Db\Helpers\DbOci8Functions;
use ByJG\AnyDataset\Db\Helpers\DbPdoFunctions;
use ByJG\AnyDataset\Db\Helpers\DbPgsqlFunctions;
use ByJG\AnyDataset\Db\Helpers\DbSqliteFunctions;
use ByJG\AnyDataset\Db\Helpers\DbSqlsrvFunctions;
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
    public function getDbHelperClass(): string
    {
        // Detect PDO driver at runtime
        $pdo = $this->getDbConnection();
        if ($pdo instanceof PDO) {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            return match ($driver) {
                'mysql' => DbMysqlFunctions::class,
                'sqlite' => DbSqliteFunctions::class,
                'pgsql' => DbPgsqlFunctions::class,
                'sqlsrv' => DbSqlsrvFunctions::class,
                'dblib' => DbDblibFunctions::class,
                'oci' => DbOci8Functions::class,
                default => DbPdoFunctions::class,
            };
        }

        return DbPdoFunctions::class;
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
