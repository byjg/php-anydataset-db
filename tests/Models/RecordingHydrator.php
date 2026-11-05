<?php

namespace Tests\Models;

use ByJG\AnyDataset\Db\Interfaces\EntityHydratorInterface;
use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;

/**
 * An entity transformer that fills the entity itself, and records what it was given
 */
class RecordingHydrator implements PropertyHandlerInterface, EntityHydratorInterface
{
    public array $calls = [];

    #[\Override]
    public function hydrate(array $row, object $entity, array $defaults): void
    {
        $this->calls[] = [$row, get_class($entity), $defaults];
        $entity->id = (int)$row['id'];
        $entity->property = 'hydrated:' . $row['property'];
    }

    #[\Override]
    public function mapName(string $property): string
    {
        throw new \LogicException('The hydrator fills the entity: the property handler is not used');
    }

    #[\Override]
    public function transformValue(string $propertyName, string $targetName, mixed $value, mixed $instance = null): mixed
    {
        throw new \LogicException('The hydrator fills the entity: the property handler is not used');
    }
}
