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
phpunit testsdb/PdoMySqlTest.php 
phpunit testsdb/PdoSqliteTest.php 
phpunit testsdb/PdoPostgresTest.php 
phpunit testsdb/PdoDblibTest.php 
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
