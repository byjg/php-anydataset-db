---
sidebar_position: 13
---

# Using a PDO Statement

If you have a PDO Statement created outside the AnyDatasetDB library,
you can use it to create an iterator.

```php
<?php
$pdo = new PDO('sqlite::memory:');
$stmt = $pdo->prepare('select * from info where id = :id');
$stmt->execute(['id' => 1]);

$iterator = $this->dbDriver->getIterator($stmt);
$this->assertEquals(
    [
        [ 'id'=> 1, 'iduser' => 1, 'number' => 10.45, 'property' => 'xxx'],
    ],
    $iterator->toArray()
);
```

## Notes

- While you can use a PDO Statement, it is recommended to use the
  `SqlStatement` or `DbDriverInterface` for executing queries whenever possible.
- This feature is best suited for legacy code or situations where using a PDO Statement is necessary.

## Benefits

Using this approach allows you to integrate the AnyDatasetDB library with your legacy code
while still taking advantage of features like the standard `GenericIterator`.
