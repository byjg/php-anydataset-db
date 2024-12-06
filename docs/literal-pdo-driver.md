---
sidebar_position: 16
---

# Literal PDO configuration

If you want to use a PDO driver and this driver is not available in the AnyDatasetDB or and it requires special
parameters don't fit well
using the URI model you can use the literal object.

The `PdoLiteral` object uses the PDO connection string instead of the URI model.

Example:

```php
<?php

$literal = new \ByJG\AnyDataset\Db\PdoLiteral("sqlite::memory:");
```

Drawbacks:

* You can't use the `Factory::getDbInstance` to get the database instance. You need to use the `PdoLiteral` object
  directly.
* The DBHelper won't work with the `PdoLiteral` object.


