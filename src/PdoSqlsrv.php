<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\Util\Uri;
use PDO;

class PdoSqlsrv extends PdoDblib
{

    public static function schema()
    {
        return ['sqlsrv'];
    }

    /**
     * PdoSqlsrv constructor.
     *
     * @param Uri $connUri
     * @throws \ByJG\AnyDataset\Core\Exception\NotAvailableException
     */
    public function __construct($connUri)
    {
        $this->setSupportMultRowset(true);

        $this->validateConnUri($connUri);

        $dsn = $connUri->getScheme() . ":"
            . "server=" . $connUri->getHost() . (!empty($connUri->getPort()) ? "," . $connUri->getPort() : "") . ";"
            . "Database=" . preg_replace('~^/~', '', $connUri->getPath());

        $this->instance = new PDO($dsn, $connUri->getUsername(), $connUri->getPassword());

        $this->setPdoDefaultParams();

        // Solve the error:
        // SQLSTATE[HY000]: General error: 1934 General SQL Server error: Check messages from the SQL Server [1934]
        // (severity 16) [(null)]
        //
        // http://gullele.wordpress.com/2010/12/15/accessing-xml-column-of-sql-server-from-php-pdo/
        // http://stackoverflow.com/questions/5499128/error-when-using-xml-in-stored-procedure-pdo-ms-sql-2008
        $this->getDbConnection()->exec('SET QUOTED_IDENTIFIER ON');
        $this->getDbConnection()->exec('SET ANSI_WARNINGS ON');
        $this->getDbConnection()->exec('SET ANSI_PADDING ON');
        $this->getDbConnection()->exec('SET ANSI_NULLS ON');
        $this->getDbConnection()->exec('SET CONCAT_NULL_YIELDS_NULL ON');
    }
}
