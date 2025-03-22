<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\Util\Uri;
use Override;

class PdoDblib extends PdoPdo
{
    #[Override]
    public static function schema(): array
    {
        return ['dblib'];
    }


    /**
     * PdoDblib constructor.
     *
     * @param Uri $connUri
     * @throws NotAvailableException
     */
    public function __construct(Uri $connUri)
    {
        $this->setSupportMultiRowset(true);

        // Run after instance is created
        // Solve the error:
        // SQLSTATE[HY000]: General error: 1934 General SQL Server error: Check messages from the SQL Server [1934]
        // (severity 16) [(null)]
        //
        // http://gullele.wordpress.com/2010/12/15/accessing-xml-column-of-sql-server-from-php-pdo/
        // http://stackoverflow.com/questions/5499128/error-when-using-xml-in-stored-procedure-pdo-ms-sql-2008
        $executeAfterConnect = [
            'SET QUOTED_IDENTIFIER ON',
            'SET ANSI_WARNINGS ON',
            'SET ANSI_PADDING ON',
            'SET ANSI_NULLS ON',
            'SET CONCAT_NULL_YIELDS_NULL ON',
        ];

        parent::__construct($this->getMssqlUri($connUri), [], [], $executeAfterConnect);
    }

    protected function getMssqlUri(Uri $connUri): Uri
    {
        /** @var Uri $uri */
        $uri = Uri::getInstanceFromString("dblib://");

        return $uri
            ->withUserInfo($connUri->getUsername(), $connUri->getPassword())
            ->withHost($connUri->getScheme())
            ->withQueryKeyValue("host", $connUri->getHost() . (!empty($connUri->getPort()) ? "," . (string)$connUri->getPort() : ""))
            ->withQueryKeyValue("dbname", ltrim($connUri->getPath(), "/"))
        ;
    }
}
