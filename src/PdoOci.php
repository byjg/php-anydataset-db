<?php

namespace ByJG\AnyDataset\Db;

use ByJG\Util\Uri;
use Override;

class PdoOci extends PdoLiteral
{

    #[Override]
    public static function schema(): array
    {
        return ['oracle'];
    }

    public function __construct(Uri $connUri)
    {
        parent::__construct($this->createPdoConnStr($connUri), $connUri->getUsername(), $connUri->getPassword(), [], []);
    }

    protected function createPdoConnStr(Uri $connUri): string
    {
        return $connUri->getScheme(). ":dbname=" . self::getTnsString($connUri);
    }

    /**
     *
     * @param Uri $connUri
     * @return string
     */
    public static function getTnsString(Uri $connUri): string
    {
        $protocol = $connUri->getQueryPart("protocol");
        $protocol = ($protocol == "") ? 'TCP' : $protocol;

        $port = $connUri->getPort() ?? 1521;

        $svcName = preg_replace('~^/~', '', $connUri->getPath());

        $host = $connUri->getHost();

        return "(DESCRIPTION = " .
            "    (ADDRESS = (PROTOCOL = $protocol)(HOST = $host)(PORT = $port)) " .
            "        (CONNECT_DATA = (SERVICE_NAME = $svcName)) " .
            ")";
    }
}
