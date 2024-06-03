<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\Util\Uri;

class PdoDblib extends DbPdoDriver
{
    public static function schema()
    {
        return ['dblib'];
    }


    /**
     * PdoDblib constructor.
     *
     * @param Uri $connUri
     * @throws NotAvailableException
     */
    public function __construct($connUri)
    {
        $this->setSupportMultRowset(true);

        $uri = Uri::getInstanceFromString("pdo://")
            ->withUserInfo($connUri->getUsername(), $connUri->getPassword())
            ->withHost($connUri->getScheme())
            ->withQueryKeyValue("server" , $connUri->getHost() . (!empty($connUri->getPort()) ? "," . $connUri->getPort() : ""))
            ->withQueryKeyValue("Database", ltrim($connUri->getPath(), "/"));

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

        parent::__construct($uri, [], [], $executeAfterConnect);
    }
}
