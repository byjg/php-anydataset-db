---
sidebar_position: 18
---

# Literal PDO configuration

If you want to use a PDO driver that is not available in AnyDatasetDB or requires special parameters that
do not align well with the URI model, you can use the `PdoLiteral` object.

The `PdoLiteral` object allows you to use a traditional PDO connection string instead of the URI model.

Example:

```php
<?php

$literal = new \ByJG\AnyDataset\Db\PdoLiteral("sqlite::memory:");
```

### Drawbacks

- You cannot use the `Factory::getDbInstance` method to obtain the database instance. Instead, you must use the
  `PdoLiteral` object directly.
- The `DBHelper` functionality is not compatible with the `PdoLiteral` object.


