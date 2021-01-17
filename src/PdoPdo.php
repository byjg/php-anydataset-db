<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\Util\Uri;
use PDO;

class PdoPdo extends DbPdoDriver
{

    /**
     * PdoPdo constructor.
     *
     * @param Uri $connUri
     * @throws NotAvailableException
     */
    public function __construct(Uri $connUri, $preOptions = [], $postOptions = [])
    {
        $this->validateConnUri($connUri, $connUri->getHost());

        if (empty($connUri->getQueryPart("dsn"))) {
            throw new \InvalidArgumentException("The generic PDO Driver requires the `dsn` argument");
        }

        $dsn = $connUri->getHost() . ":" . $connUri->getQueryPart("dsn");

        $this->instance = new PDO($dsn, $connUri->getUsername(), $connUri->getPassword(), $preOptions);

        $this->setPdoDefaultParams($postOptions);
    }
}
