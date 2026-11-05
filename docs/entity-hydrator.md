---
sidebar_position: 18
sidebar_label: Filling entities yourself
---

# How to fill the entities yourself: EntityHydratorInterface

With [Entity Mapping](entity.md), each row is copied into a new entity by `ByJG\Serializer\ObjectCopy`. With an
entity transformer (`withEntityTransformer()`), `ObjectCopy` calls it for **each value of each row**: `mapName()`
to get the property, `transformValue()` to get the value, then it resolves how to write it.

A transformer that also implements `ByJG\AnyDataset\Db\Interfaces\EntityHydratorInterface` fills each entity
itself, once per row. It can resolve the mapping once per query and make each row a single loop: reading
entities is several times faster (byjg/micro-orm does this).

**You do not need it** to use entities: without a transformer, or with a transformer that does not implement it,
the rows are copied by `ObjectCopy` as always. Your entity classes never change. Implement it in a transformer
when reading many entities matters.

## The contract

```php
interface EntityHydratorInterface
{
    /**
     * @param array $row The columns of the row (lower-case names)
     * @param object $entity A new instance of the entity class
     * @param array $defaults The properties of a new instance of the entity class, read once per query, at
     *                        the first level (an object the constructor sets stays an object)
     */
    public function hydrate(array $row, object $entity, array $defaults): void;
}
```

`hydrate()` must leave the entity with **the same values** as:

```php
ObjectCopy::copy(array_merge($defaults, $row), $entity, $transformer);
```

that is, for each key of `array_merge($defaults, $row)`, in that order: the name from `mapName()`, the value from
`transformValue()` (which gets the whole merged array as `$instance`), written as `ObjectCopy` writes it.
`ObjectCopy::writerFor($className, $name)` tells how: `[member, isSetter, enumClass]` for the setter (any case),
else the public property (exact case, else any case), or `false` when the class has neither.

## Example

The entity, as any other: nothing to implement.

```php
<?php
enum UserStatus: string
{
    case Active = 'A';
    case Blocked = 'B';
}

class User
{
    public ?int $id = null;
    protected ?string $name = null;
    public ?UserStatus $status = null;

    public function setName(?string $name): void { $this->name = $name; }
    public function getName(): ?string { return $this->name; }
}
```

The transformer: the columns `user_id` and `full_name` are the properties `id` and `name`, and the name is
trimmed. It is a property handler (for `ObjectCopy`) and an entity hydrator:

```php
<?php
use ByJG\AnyDataset\Db\Interfaces\EntityHydratorInterface;
use ByJG\Serializer\ObjectCopy;
use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;

class UserMapping implements PropertyHandlerInterface, EntityHydratorInterface
{
    /** The column => property names that differ */
    private const COLUMNS = ['user_id' => 'id', 'full_name' => 'name'];

    /** How each column is written, resolved at the first row */
    private array $plan = [];

    public function mapName(string $property): string
    {
        return self::COLUMNS[$property] ?? $property;
    }

    public function transformValue(string $propertyName, string $targetName, mixed $value, mixed $instance = null): mixed
    {
        return $targetName === 'name' && is_string($value) ? trim($value) : $value;
    }

    public function hydrate(array $row, object $entity, array $defaults): void
    {
        $source = array_merge($defaults, $row);
        foreach ($source as $key => $value) {
            $key = (string)$key;
            if (!isset($this->plan[$key])) {
                $name = $this->mapName($key);
                $this->plan[$key] = [$name, ObjectCopy::writerFor(get_class($entity), $name)];
            }
            [$name, $writer] = $this->plan[$key];

            $value = $this->transformValue($key, $name, $value, $source);

            if ($writer === false) {
                // No setter nor public property: only a property the instance has (dynamic or magic)
                if (isset($entity->{$name})) {
                    $entity->{$name} = $value;
                }
                continue;
            }

            [$member, $isSetter, $enum] = $writer;
            if ($enum !== null && (is_int($value) || is_string($value))) {
                $value = $enum::from($value);
            }
            if ($isSetter) {
                $entity->{$member}($value);
            } else {
                $entity->{$member} = $value;
            }
        }
    }
}
```

Use it as any transformer:

```php
<?php
use ByJG\AnyDataset\Db\SqlStatement;

// users: user_id | full_name  | status | created
//        1       | '  John  ' | 'A'    | 2024-01-01
$sql = (new SqlStatement('SELECT * FROM users'))
    ->withEntityClass(User::class)
    ->withEntityTransformer(new UserMapping());

foreach ($executor->getIterator($sql) as $row) {
    $user = $row->entity();
    $user->id;            // 1
    $user->getName();     // 'John'
    $user->status;        // UserStatus::Active
}
```

`created` has no property in `User`: `writerFor()` gives `false` and it is skipped, as `ObjectCopy` does.

## Notes

- **The plan belongs to one entity class.** It is resolved from the entity's class at the first row: use a
  transformer instance for one entity class (a new instance per query is the simplest).
- **Do the per-row work only.** What depends only on the column name and the class (the property, how it is
  written, a function to apply) goes in the plan; the loop applies it.
- **The values of a database row are scalars or null.** `ObjectCopy` also turns a `BackedEnum` *value* of the
  source into its value; add `if ($value instanceof BackedEnum) { $value = $value->value; }` if your rows can
  have one.
- **`$defaults`** are the properties of one new entity, read once per query: the properties without a column get
  those values (see [Entity Mapping](entity.md#property-mapping)).

## Test that it fills as ObjectCopy does

Compare `hydrate()` with `ObjectCopy::copy()` and the same transformer, on all the properties (protected
included), for the rows your entities can get: nulls, every column type, a column without property.

```php
<?php
use ByJG\Serializer\ObjectCopy;
use ByJG\Serializer\Serialize;

public function testHydrateFillsAsObjectCopy(): void
{
    $defaults = Serialize::from(new User())->withStopAtFirstLevel()->toArray();   // as the iterator reads them
    $hydrator = new UserMapping();
    $handler = new UserMapping();

    foreach ([
        ['user_id' => 1, 'full_name' => ' John ', 'status' => 'A', 'created' => '2024-01-01'],
        ['user_id' => 2, 'full_name' => null, 'status' => null, 'created' => null],
    ] as $row) {
        $hydrated = new User();
        $hydrator->hydrate($row, $hydrated, $defaults);

        $copied = new User();
        ObjectCopy::copy(array_merge($defaults, $row), $copied, $handler);

        $this->assertEquals((array)$copied, (array)$hydrated);
    }
}
```

byjg/micro-orm's `MapFromDbToInstanceHandler` is a complete implementation (with field mappings and select
functions), tested this way by `MapFromDbToInstanceHandlerTest`.
