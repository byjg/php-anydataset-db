# Literal PDO configuration

If you want to use a PDO driver, and it requires special parameters don't fit well using the URI model you can use the literal object.
It will allow you to pass the PDO connection string directly.

Example:

```php
<?php

$literal = new \ByJG\AnyDataset\Db\PdoLiteral("sqlite::memory:");
```

The general rule is use the string as you would use in the PDO constructor.


