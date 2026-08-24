---
sidebar_position: 26
---

# Driver: Cloudflare D1

[Cloudflare D1](https://developers.cloudflare.com/d1/) is a SQLite compatible database served from
the Cloudflare edge. It has no wire protocol and therefore no PDO driver: every statement is a POST
to the [D1 REST API](https://developers.cloudflare.com/api/resources/d1/subresources/database/methods/query/).

The `DbD1Driver` implements the regular `DbDriverInterface` on top of that API, so D1 is used
exactly like any other database in this library.

## Requirements

The driver needs a [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client. If you do not inject
one, it uses `byjg/webrequest`:

```bash
composer require byjg/webrequest
```

## Connection String

```php
<?php
$conn = \ByJG\AnyDataset\Db\Factory::getDbInstance(
    "d1://{account_id}:{api_token}@api.cloudflare.com/{database_id}"
);
```

| URI part   | Meaning                                                                 |
|------------|-------------------------------------------------------------------------|
| user       | Your Cloudflare **account id**                                            |
| password   | An **API token** with the `D1:Edit` permission                            |
| host       | The API host. Use `api.cloudflare.com` unless you are pointing at a mock  |
| path       | The **database id** (the UUID shown by `wrangler d1 list`)                |

Optional query parameters:

| Parameter   | Default       | Description                                        |
|-------------|---------------|----------------------------------------------------|
| `apischeme` | `https`       | Use `http` to reach a local mock server             |
| `basepath`  | `/client/v4`  | The API base path                                   |

Because the API token is a credential, read it from the environment instead of hardcoding it:

```php
<?php
$conn = \ByJG\AnyDataset\Db\Factory::getDbInstance(sprintf(
    "d1://%s:%s@api.cloudflare.com/%s",
    getenv('D1_ACCOUNT_ID'),
    getenv('D1_API_TOKEN'),
    getenv('D1_DATABASE_ID')
));
```

Create the token at **My Profile → API Tokens** in the Cloudflare dashboard, and find the account
and database ids with `npx wrangler d1 info <DATABASE_NAME>`.

## Usage

Nothing changes compared to the other drivers:

```php
<?php
$executor = \ByJG\AnyDataset\Db\DatabaseExecutor::using($conn);

$id = $executor->executeAndGetId(
    "INSERT INTO Dogs (Breed, Name) VALUES (:breed, :name)",
    ['breed' => 'Mutt', 'name' => 'Spyke']
);

foreach ($executor->getIterator("SELECT * FROM Dogs WHERE Breed = :breed", ['breed' => 'Mutt']) as $row) {
    echo $row->get('name');
}
```

Named parameters are translated into the positional `?` placeholders the D1 API expects, so you
write the same SQL as everywhere else in the library.

## Using a different HTTP client

Any PSR-18 client works. Inject it before running the first query — useful to configure proxies,
timeouts or retries, and to test without hitting the network:

```php
<?php
/** @var \ByJG\AnyDataset\Db\DbD1Driver $conn */
$conn->setHttpClient($myPsr18Client);
```

## Read replication

D1 can serve reads from replicas in other regions. This is **opt-in**: it is off until you set
`"read_replication": {"mode": "auto"}` on the database, in the dashboard or through the REST API.
While it is disabled every query goes to the primary and there is nothing to worry about.

**Before you enable it, read this.** Cloudflare keeps replicas consistent with the
[Sessions API](https://developers.cloudflare.com/d1/best-practices/read-replication/#use-sessions-api),
which threads a *bookmark* (a database version token) through your queries so that a read is never
served by a replica that is behind a write you already made — giving you read-your-own-writes and
monotonic reads.

The Sessions API is **only available through the D1 Workers binding, not through the REST API**
this driver uses. There is no bookmark field on the `/query` endpoint, so the driver cannot request
sequential consistency. If you enable read replication, reads issued through this driver are
eventually consistent: a `SELECT` may not see an `INSERT` you just made.

If you need read-your-own-writes, keep read replication disabled.

### Observing where a query was served

The driver exposes the routing information D1 reports, so you can check empirically what is
happening:

```php
<?php
/** @var \ByJG\AnyDataset\Db\DbD1Driver $conn */
$executor->getIterator("SELECT * FROM Dogs")->toArray();

$conn->isServedByPrimary();   // true, false, or null when D1 does not report it
$conn->getServedByRegion();   // "WNAM", "ENAM", "WEUR", "EEUR", "APAC", "OC" or null
$conn->getServedByColo();     // "LHR" or null
$conn->getLastMeta();         // the full meta object: duration, rows_read, rows_written, ...
```

All of them describe the **last executed statement**, and return `null` (or an empty array for
`getLastMeta()`) before anything has run or when the API omits the field.

## Limitations

D1 is not a drop-in replacement for a local SQLite file. The following differences are intentional
and the driver fails loudly rather than pretending otherwise.

- **No interactive transactions.** The REST API rejects `BEGIN`/`COMMIT` with *"cannot start a
  transaction within a transaction"*, because every request is already wrapped in its own implicit
  transaction. `beginTransaction()` throws a `NotAvailableException`. Each statement is executed and
  committed atomically on its own.
- **No multiple rowsets.** `isSupportMultiRowset()` is always `false`.
- **`getAllFields()` is not available.** It relies on column metadata that the API does not return
  for an empty result set. Use `$executor->getHelper()->getTableMetadata($executor, 'Dogs')`
  instead, which reads `PRAGMA table_info`.
- **The Journal does not track INSERTs.** `JournalRecorder` locates the inserted row with
  `SELECT last_insert_rowid()`, which would be a second HTTP request on a different connection and
  always answers `0`. `executeAndGetId()` is unaffected: it reads the id from D1's own
  `meta.last_row_id`. In strict mode the Journal throws instead of recording a wrong value.
- **`FOR UPDATE` is not available**, as in SQLite.
- **No Sessions API**, so no sequential consistency when read replication is enabled. See
  [Read replication](#read-replication) above.

D1's own [platform limits](https://developers.cloudflare.com/d1/platform/limits/) also apply, most
notably:

| Limit                        | Value                |
|------------------------------|----------------------|
| Maximum SQL statement length | 100,000 bytes         |
| Maximum bound parameters     | 100 per query         |
| Maximum query duration       | 30 seconds            |
| Maximum row / string / BLOB  | 2,000,000 bytes       |
| Maximum columns per table    | 100                   |
