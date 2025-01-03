<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\Util\Uri;

class PdoSqlsrv extends PdoDblib
{

    public static function schema(): array
    {
        return ['sqlsrv'];
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

    protected function getMssqlUri(Uri $connUri): Uri
    {
        /** @var Uri $uri */
        $uri = Uri::getInstanceFromString("pdo://");

        return $uri
            ->withUserInfo($connUri->getUsername(), $connUri->getPassword())
            ->withHost($connUri->getScheme())
            ->withQueryKeyValue("Server" , $connUri->getHost() . (!empty($connUri->getPort()) ? "," . $connUri->getPort() : ""))
            ->withQueryKeyValue("Database", ltrim($connUri->getPath(), "/"))
            ->withQueryKeyValue('TrustServerCertificate', 'true')
        ;
    }
}
