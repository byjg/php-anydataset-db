<?php

namespace Test\Models;

/**
 * Entity whose typed properties are non-nullable and have no default value. A freshly
 * constructed instance keeps them in the "uninitialized" state. PreFetchTrait serializes an
 * empty instance (Serialize::from(new self())->toArray()) to enumerate the fields before
 * hydration, which used to raise "Typed property ... must not be accessed before initialization".
 *
 * The property names match the `users` table columns, so no transformer is required.
 */
class UserTypedEntity
{
    public int $id;

    public string $name;

    public string $createdate;
}