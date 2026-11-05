<?php

namespace ByJG\AnyDataset\Db;

use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;
use Generator;
use Override;
use PDO;
use PDOStatement;

class DbIterator extends GenericDbIterator
{
    /**
     * @param PDOStatement $recordset
     * @param string|null $entityClass
     * @param PropertyHandlerInterface|null $entityTransformer
     */
    public function __construct(PDOStatement $recordset, ?string $entityClass = null, ?PropertyHandlerInterface $entityTransformer = null)
    {
        parent::__construct($recordset, $entityClass, $entityTransformer);
    }

    /**
     * @return Generator<int, array>
     */
    #[Override]
    protected static function fetch(mixed $cursor): Generator
    {
        assert($cursor instanceof PDOStatement);
        try {
            while (is_array($row = $cursor->fetch(PDO::FETCH_ASSOC))) {
                yield $row;
            }
        } finally {
            $cursor->closeCursor();
        }
    }
}
