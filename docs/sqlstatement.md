---
sidebar_position: 3
---

# SQL Statement

The SQL Statement is a class to abstract the SQL query from the database.

```php
<?php

$dbDriver = Factory::getDbInstance("mysql://user:password@server/schema");
$sql = new SqlStatement("select * from table where field = :param");

$iterator = $sql->getIterator($dbDriver, ['param' => 'value']);
```

The advantage of using the `SqlStatement` is that you can reuse the same SQL statement with different parameters.
It saves time preparing the cache.

Also, you can cache the query (see [Cache results](cache.md)).

 
