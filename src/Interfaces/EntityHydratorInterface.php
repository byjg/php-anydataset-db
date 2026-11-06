<?php

namespace ByJG\AnyDataset\Db\Interfaces;

/**
 * An entity transformer (SqlStatement::withEntityTransformer()) that also fills the entity of each row itself,
 * instead of GenericDbIterator copying the row into it with ByJG\Serializer\ObjectCopy and the transformer as the
 * property handler. It must give the entity the same values as that copy would: the columns of the row merged over
 * the properties of a new entity ($defaults), each by its mapped name (mapName()) and value (transformValue()).
 *
 * For an ORM that knows its mapping and can compile it once per query.
 */
interface EntityHydratorInterface
{
    /**
     * @param array $row The columns of the row (lower-case names)
     * @param object $entity A new instance of the entity class
     * @param array $defaults The properties of a new instance of the entity class, read once per query, at the
     *                        first level (Serialize::from(new $entityClass())->withStopAtFirstLevel()->toArray())
     */
    public function hydrate(array $row, object $entity, array $defaults): void;
}
