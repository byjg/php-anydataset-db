---
sidebar_position: 12
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

Note:

* Although you can use a PDO Statement, it is recommended to use the
  `SqlStatement` or `DbDriverInterface` to get the Query.
* Use this feature with legacy code or when you have a specific need to use a PDO Statement.

## Benefits

You can integrate the AnyDatasetDB library with your legacy code and get the benefits of the library
as for example the standard `GenericIterator` 



