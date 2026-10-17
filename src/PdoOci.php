<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\SqlDialect\OciDialect;
use ByJG\Util\Uri;
use Override;
use PDOStatement;

class PdoOci extends DbPdoDriver
{
    protected Uri $connUri;

    #[Override]
    public static function schema(): array
    {
        return ['oci', 'oracle'];
    }

    #[Override]
    public function getSqlDialectClass(): string
    {
        return OciDialect::class;
    }

    /**
     * PdoOci constructor.
     *
     * Ex.
     *
     *    oci://username:password@host:1521/servicename?protocol=TCP&codepage=AL32UTF8
     *
     * @param Uri $connUri
     * @throws DbDriverNotConnected
     * @throws NotAvailableException
     */
    public function __construct(Uri $connUri)
    {
        $this->connUri = $connUri;

        parent::__construct($this->getOciUri($connUri));
    }

    #[Override]
    public function getUri(): Uri
    {
        return $this->connUri;
    }

    /**
     * Oracle rejects a statement that ends with a semicolon.
     */
    #[Override]
    public function prepareStatement(string $sql, ?array $params = null, ?array &$cacheInfo = []): PDOStatement
    {
        return parent::prepareStatement(rtrim($sql, ' ;'), $params, $cacheInfo);
    }

    protected function getOciUri(Uri $connUri): Uri
    {
        $codePage = $connUri->getQueryPart("codepage");

        $uri = Uri::getInstance("pdo://");

        return $uri
            ->withUserInfo($connUri->getUsername() ?? '', $connUri->getPassword())
            ->withHost("oci")
            ->withQueryKeyValue("dbname", DbOci8Driver::getTnsString($connUri))
            ->withQueryKeyValue("charset", empty($codePage) ? 'AL32UTF8' : $codePage)
        ;
    }
}
