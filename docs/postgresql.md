---
sidebar_position: 17
---

# Driver: PostgreSQL

The connection string can have special attributes to connect using SSL.

## Connecting To PostgreSQL via SSL

```php
<?php
$sslMode = "require";             // disable, allow, prefer, require, verify-ca, verify-full
$sslCert = "/path/to/client.crt"; // Path to client certificate file
$sslKey = "/path/to/client.key";  // Path to client private key file
$sslRootCert = "/path/to/ca.crt"; // Path to root CA certificate
$sslCrl = "/path/to/crl.pem";     // Path to certificate revocation list

$db = \ByJG\AnyDataset\Db\Factory::getDbInstance(
    "postgresql://localhost/database?sslmode=$sslMode&sslcert=$sslCert&sslkey=$sslKey&sslrootcert=$sslRootCert&sslcrl=$sslCrl"
);

$iterator = $db->getIterator('select * from table where field = :value', ['value' => 10]);
foreach ($iterator as $row) {
    // Do Something
    // $row->getField('field');
}
```

### SSL Mode Options

- `disable`: SSL connection is not attempted
- `allow`: SSL connection is attempted, but will connect without SSL if not available
- `prefer`: SSL connection is attempted, but will connect without SSL if not available (default)
- `require`: SSL connection is required
- `verify-ca`: SSL connection is required and server certificate is verified
- `verify-full`: SSL connection is required, server certificate is verified and hostname is verified 