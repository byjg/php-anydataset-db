<?php

namespace ByJG\AnyDataset\Db;

use ByJG\Util\Uri;

class PdoPdo extends DbPdoDriver
{

    /**
     * PdoPdo constructor.
     *
     * @param \ByJG\Util\Uri $connUri
     * @throws \ByJG\AnyDataset\Core\Exception\NotAvailableException
     */
    public function __construct(Uri $connUri, $preOptions = [], $postOptions = [])
    {
        $this->connectionUri = $connUri;

        if (empty($connUri->getQueryPart("dsn"))) {
            throw new \InvalidArgumentException("The generic PDO Driver requires the `dsn` argument");
        }

        $dsn = $connUri->getHost() . ":" . $connUri->getQueryPart("dsn");

        $this->instance = new \PDO($dsn, $connUri->getUsername(), $connUri->getPassword(), $preOptions);

        foreach ((array) $postOptions as $key => $value) {
            $this->instance->setAttribute($key, $value);
        }
    }
}
