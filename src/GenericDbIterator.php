<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Core\Row;
use ByJG\AnyDataset\Core\RowInterface;
use ByJG\AnyDataset\Db\Interfaces\EntityHydratorInterface;
use ByJG\Serializer\ObjectCopy;
use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;
use ByJG\Serializer\Serialize;
use Generator;
use Override;

/**
 * The rows of a database cursor, fetched one at a time while they are read. The cursor is released when the
 * last row was read or the iterator is destroyed (getScalar(), first(), a foreach with break...).
 */
abstract class GenericDbIterator extends GenericIterator
{
    /**
     * @param mixed $cursor The cursor of the driver, given to fetch()
     * @param string|null $entityClass The class of the entity of each row; null keeps the row as an array
     * @param PropertyHandlerInterface|null $entityTransformer Maps the columns to the entity properties
     */
    public function __construct(
        private mixed $cursor,
        protected ?string $entityClass = null,
        protected ?PropertyHandlerInterface $entityTransformer = null
    ) {
    }

    /**
     * The rows of the cursor, each an array of column => value. It releases the cursor in a `finally`: it runs
     * after the last row, or when the iterator is destroyed before it.
     *
     * Static: a generator of a method would keep the iterator in memory, and the cursor open (see
     * GenericIterator::rows()).
     *
     * @return Generator<int, array>
     */
    abstract protected static function fetch(mixed $cursor): Generator;

    /**
     * The cursor still has rows to read; false once the last row was read and the cursor released
     */
    public function isCursorOpen(): bool
    {
        return $this->valid();
    }

    #[Override]
    protected function rows(): Generator
    {
        return self::walk(static::fetch($this->cursor), $this->entityClass, $this->entityTransformer);
    }

    private static function walk(
        Generator $rows,
        ?string $entityClass,
        ?PropertyHandlerInterface $entityTransformer
    ): Generator {
        $defaults = null;
        foreach ($rows as $row) {
            if (!empty($entityClass)) {
                // The properties of a new entity, the same for every row: listed once, at the first row. At the
                // first level: an object the constructor sets stays that object, not an array
                $defaults ??= Serialize::from(new $entityClass())->withStopAtFirstLevel()->toArray();
            }
            yield self::createRow(array_change_key_case($row), $entityClass, $entityTransformer, $defaults ?? []);
        }
    }

    /**
     * @param array $fieldValues The columns of the row
     * @param string|null $entityClass
     * @param PropertyHandlerInterface|null $entityTransformer
     * @param array $defaults The properties of a new $entityClass (Serialize::from(new $entityClass())->toArray())
     */
    protected static function createRow(
        array $fieldValues,
        ?string $entityClass,
        ?PropertyHandlerInterface $entityTransformer,
        array $defaults = []
    ): RowInterface {
        if (empty($entityClass)) {
            return new Row($fieldValues);
        }

        $entity = new $entityClass();
        if ($entityTransformer instanceof EntityHydratorInterface) {
            $entityTransformer->hydrate($fieldValues, $entity, $defaults);
            return new Row($entity);
        }

        // All the properties of the class, not only the columns: the ones without a column are processed too
        // (particularly useful for the selectFunction)
        ObjectCopy::copy(array_merge($defaults, $fieldValues), $entity, $entityTransformer);
        return new Row($entity);
    }
}
