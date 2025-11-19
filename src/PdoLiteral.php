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

class PdoLiteral extends DbPdoDriver
{

    #[Override]
    public static function schema()
    {
        return null;
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
