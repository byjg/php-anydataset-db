---
sidebar_position: 16
---

# Running Unit tests

## Unit Tests (no DBConnection)

```bash
vendor/bin/phpunit
```

## Running database tests

Run integration tests require you to have the databases up and running. We provided a basic `docker-compose.yml` and you
can use to start the databases for test.

### Starting the target databases

```bash
docker-compose up -d postgres mysql
```

### Running the tests against the databases

```bash
vendor/bin/testsdb/PdoMySqlTest.php 
vendor/bin/testsdb/PdoSqliteTest.php 
vendor/bin/testsdb/PdoPostgresTest.php 
vendor/bin/testsdb/PdoDblibTest.php 
vendor/bin/testsdb/PdoSqlsrvTest.php 
```

Optionally you can set the host and password used by the unit tests

```bash
export MYSQL_TEST_HOST=localhost     # defaults to localhost
export MYSQL_PASSWORD=newpassword    # use '.' if want have a null password
export PSQL_TEST_HOST=localhost      # defaults to localhost
export PSQL_PASSWORD=newpassword     # use '.' if want have a null password
export MSSQL_TEST_HOST=localhost     # defaults to localhost
export MSSQL_PASSWORD=Pa55word            
export SQLITE_TEST_HOST=/tmp/test.db      # defaults to /tmp/test.db
```

### Cloudflare D1

D1 has no local emulator exposing the Cloudflare REST API, so `testsdb/D1Test.php` runs against a
real (throwaway) D1 database and is skipped unless all three variables below are set. The driver
itself is fully covered without credentials by `tests/D1DriverTest.php`.

```bash
export D1_ACCOUNT_ID=your-account-id
export D1_DATABASE_ID=your-database-uuid
export D1_API_TOKEN=your-api-token
export D1_HOST=api.cloudflare.com    # defaults to api.cloudflare.com
```
