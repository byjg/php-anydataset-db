<?php

namespace ByJG\AnyDataset\Db;

use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;
use Generator;
use Override;

/**
 * Iterates over the rows returned by the Cloudflare D1 HTTP API.
 *
 * The D1 endpoint answers with the whole result set at once, so there is no server-side cursor to
 * keep open. The rows are consumed one at a time anyway, as in the cursor based iterators.
 */
class D1Iterator extends GenericDbIterator
{
    public function __construct(
        D1Statement $statement,
        ?string $entityClass = null,
        ?PropertyHandlerInterface $entityTransformer = null
    ) {
        parent::__construct($statement->getRows(), $entityClass, $entityTransformer);
    }

    /**
     * @return Generator<int, array>
     */
    #[Override]
    protected static function fetch(mixed $cursor): Generator
    {
        assert(is_array($cursor));
        yield from $cursor;
    }
}
