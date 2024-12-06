---
sidebar_position: 13
---

# Driver: MySQL

The connection string can have special attributes to connect using SSL. 

## Connecting To MySQL via SSL

Read [here](https://gist.github.com/byjg/860065a828150caf29c20209ecbd5692) about create SSL mysql

```php
<?php
$sslCa = "/path/to/ca";
$sslKey = "/path/to/Key";
$sslCert = "/path/to/cert";
$sslCaPath = "/path";
$sslCipher = "DHE-RSA-AES256-SHA:AES128-SHA";
$verifySsl = 'false';  // or 'true'. Since PHP 7.1.

$db = \ByJG\AnyDataset\Db\Factory::getDbInstance(
    "mysql://localhost/database?ca=$sslCa&key=$sslKey&cert=$sslCert&capath=$sslCaPath&verifyssl=$verifySsl&cipher=$sslCipher"
);

$iterator = $db->getIterator('select * from table where field = :value', ['value' => 10]);
foreach ($iterator as $row) {
    // Do Something
    // $row->getField('field');
}
```
