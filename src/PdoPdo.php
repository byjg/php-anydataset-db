<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\Util\Uri;
use Override;

class PdoPdo extends DbPdoDriver
{

    #[Override]
    public static function schema(): array
    {
        return ['pdo'];
    }

    /**
     * PdoPdo constructor.
     *
     * @param Uri $connUri
     * @param array|null $preOptions
     * @param array|null $postOptions
     * @param array $executeAfterConnect
     * @throws DbDriverNotConnected
     */
    public function __construct(Uri $connUri, ?array $preOptions = [], ?array $postOptions = [], array $executeAfterConnect = [])
    {
        parent::__construct($connUri, $preOptions, $postOptions, $executeAfterConnect);
    }
}
