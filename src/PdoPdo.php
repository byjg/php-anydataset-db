<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\Util\Uri;

class PdoPdo extends DbPdoDriver
{

    public static function schema()
    {
        return ['pdo'];
    }

    /**
     * PdoPdo constructor.
     *
     * @param Uri $connUri
     * @param array $preOptions
     * @param array $postOptions
     * @throws NotAvailableException
     */
    public function __construct(Uri $connUri, $preOptions = [], $postOptions = [])
    {
        parent::__construct($connUri, $preOptions, $postOptions);
    }
}
