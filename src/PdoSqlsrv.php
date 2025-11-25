<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\SqlDialect\SqlsrvDialect;
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
    public function getSqlDialectClass(): string
    {
        return SqlsrvDialect::class;
    }

    /**
     * PdoSqlsrv constructor.
     *
     * @param Uri $connUri
     * @throws DbDriverNotConnected
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
            ->withUserInfo($connUri->getUsername() ?? '', $connUri->getPassword())
            ->withHost($connUri->getScheme())
            ->withQueryKeyValue("Server", $connUri->getHost() . (!empty($connUri->getPort()) ? "," . strval($connUri->getPort()) : ""))
            ->withQueryKeyValue("Database", ltrim($connUri->getPath(), "/"))
            ->withQueryKeyValue('TrustServerCertificate', 'true')
        ;
    }
}
