<?php

namespace ByJG\AnyDataset\Db;

use ByJG\Util\Uri;

class PdoOci extends DbPdoDriver
{

    public static function schema()
    {
        return ['oci'];
    }

    public function __construct(Uri $connUri)
    {
        parent::__construct($connUri);
    }

    protected function createPdoConnStr(Uri $connUri)
    {
        return $connUri->getScheme(). ":dbname=" . self::getTnsString($connUri);
    }

    /**
     *
     * @param Uri $connUri
     * @return string
     */
    public static function getTnsString(Uri $connUri)
    {
        $protocol = $connUri->getQueryPart("protocol");
        $protocol = ($protocol == "") ? 'TCP' : $protocol;

        $port = $connUri->getPort();
        $port = ($port == "") ? 1521 : $port;

        $svcName = preg_replace('~^/~', '', $connUri->getPath());

        $host = $connUri->getHost();

        return "(DESCRIPTION = " .
            "    (ADDRESS = (PROTOCOL = $protocol)(HOST = $host)(PORT = $port)) " .
            "        (CONNECT_DATA = (SERVICE_NAME = $svcName)) " .
            ")";
    }
}
