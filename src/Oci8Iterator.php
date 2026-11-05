<?php

namespace ByJG\AnyDataset\Db;

use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;
use Generator;
use Override;

class Oci8Iterator extends GenericDbIterator
{
    /**
     * @param resource $cursor
     * @param string|null $entityClass
     * @param PropertyHandlerInterface|null $entityTransformer
     */
    public function __construct($cursor, ?string $entityClass = null, ?PropertyHandlerInterface $entityTransformer = null)
    {
        parent::__construct($cursor, $entityClass, $entityTransformer);
    }

    /**
     * @return Generator<int, array>
     */
    #[Override]
    protected static function fetch(mixed $cursor): Generator
    {
        assert(is_resource($cursor));
        try {
            while (is_array($row = oci_fetch_assoc($cursor))) {
                yield $row;
            }
        } finally {
            oci_free_statement($cursor);
        }
    }
}
