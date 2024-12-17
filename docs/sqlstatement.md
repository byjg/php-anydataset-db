---
sidebar_position: 3
---

# SQL Statement

The `SqlStatement` class provides an abstraction for executing SQL queries on the database.

```php
<?php

$dbDriver = Factory::getDbInstance("mysql://user:password@server/schema");
$sql = new SqlStatement("select * from table where field = :param");

$iterator = $sql->getIterator($dbDriver, ['param' => 'value']);
```

## Advantages of Using SqlStatement

- Reusability: The same SQL statement can be reused with different parameters, reducing the overhead of preparing new
  queries.
- Performance: Reusing statements helps optimize performance by leveraging caching mechanisms.
- Caching Support: Queries can be cached for even faster retrieval (see [Cache results](cache.md)).


 
