<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\Helpers\DbSqlsrvFunctions;
use ByJG\Util\Uri;
use Override;

class PdoSqlsrv extends PdoDblib
{

    #[Override]
    public static function schema(): array
    {
        return ['sqlsrv'];
    }

    #[Override]
    public function getDbHelperClass(): string
    {
        return DbSqlsrvFunctions::class;
    }

    /**
     * PdoSqlsrv constructor.
     *
     * @param Uri $connUri
     * @throws NotAvailableException
     */
    public function __construct(Uri $connUri)
    {
        parent::__construct($connUri);
    }

    #[Override]
    protected function getMssqlUri(Uri $connUri): Uri
    {
        /** @var Uri $uri */
        $uri = Uri::getInstance("pdo://");

        return $uri
            ->withUserInfo($connUri->getUsername(), $connUri->getPassword())
            ->withHost($connUri->getScheme())
            ->withQueryKeyValue("Server", $connUri->getHost() . (!empty($connUri->getPort()) ? "," . (string)$connUri->getPort() : ""))
            ->withQueryKeyValue("Database", ltrim($connUri->getPath(), "/"))
            ->withQueryKeyValue('TrustServerCertificate', 'true')
        ;
    }
}
