# AnyDataset-DB

[![Build Status](https://github.com/byjg/php-anydataset-db/actions/workflows/phpunit.yml/badge.svg?branch=master)](https://github.com/byjg/php-anydataset-db/actions/workflows/phpunit.yml)
[![Opensource ByJG](https://img.shields.io/badge/opensource-byjg-success.svg)](http://opensource.byjg.com)
[![GitHub source](https://img.shields.io/badge/Github-source-informational?logo=github)](https://github.com/byjg/php-anydataset-db/)
[![GitHub license](https://img.shields.io/github/license/byjg/php-anydataset-db.svg)](https://opensource.byjg.com/opensource/licensing.html)
[![GitHub release](https://img.shields.io/github/release/byjg/php-anydataset-db.svg)](https://github.com/byjg/php-anydataset-db/releases/)

**AnyDataset-DB** provides a relational database abstraction layer. It is part of the Anydataset project, an agnostic
data source abstraction layer for PHP.

Learn more about Anydataset [here](https://opensource.byjg.com/anydataset).

## Features

- Connection based on URI
- Handles compatibility and code optimization across multiple databases (e.g., MySQL, PostgreSQL, MS SQL Server)
- Built-in Query Cache support using a PSR-6 compliant interface
- Enables connection routing based on regular expressions for queries (e.g., directing queries to different databases
  for specific tables)

## Connection Based on URI

Database connections are defined using URL-based connection strings.

Supported drivers are listed below:

| Database            | Connection String                                 | Factory Method              |
|---------------------|---------------------------------------------------|-----------------------------|
| SQLite              | sqlite:///path/to/file                            | `getDbRelationalInstance()` |
| MySQL/MariaDB       | mysql://username:password@hostname:port/database  | `getDbRelationalInstance()` |
| PostgreSQL          | psql://username:password@hostname:port/database   | `getDbRelationalInstance()` |
| SQL Server (DbLib)  | dblib://username:password@hostname:port/database  | `getDbRelationalInstance()` |
| SQL Server (Sqlsrv) | sqlsrv://username:password@hostname:port/database | `getDbRelationalInstance()` |
| Oracle (OCI8)       | oci8://username:password@hostname:port/database   | `getDbRelationalInstance()` |
| Generic PDO         | pdo://username:password@pdo_driver?PDO_PARAMETERS | `getDbRelationalInstance()` |

Example usage:

```php
<?php
$conn = \ByJG\AnyDataset\Db\Factory::getDbInstance("mysql://root:password@10.0.1.10/myschema");
```

## Examples

- [Getting Started](docs/getting-started.md)
- [Basic Query and Update](docs/basic-query.md)
- [Sql Statement Object](docs/sqlstatement.md)
- [Cache results](docs/cache.md)
- [Database Transaction](docs/transaction.md)
- [Load Balance and Connection Pooling](docs/load-balance.md)
- [Database Helper](docs/helper.md)
- [Filtering the Query](docs/iteratorfilter.md)

## Advanced Topics

- [Passing Parameters to PDODriver](docs/parameters.md)
- [Generic PDO Driver](docs/generic-pdo-driver.md)
- [Running Tests](docs/tests.md)
- [Getting an Iterator from an existing PDO Statament](docs/pdostatement.md)
- [Pre Fetch records](docs/prefetch.md)

## Database Specifics

- [MySQL](docs/mysql.md)
- [Oracle](docs/oracle.md)
- [SQLServer](docs/sqlserver.md)
- [Literal PDO Connection String](docs/literal-pdo-driver.md)


## Install

Just type:

```bash
composer require "byjg/anydataset"
```

## Dependencies

```mermaid
flowchart TD
    byjg/anydataset-db --> byjg/anydataset-array
    byjg/anydataset-db --> ext-pdo
    byjg/anydataset-db --> byjg/uri
    byjg/anydataset-db --> psr/cache
    byjg/anydataset-db --> psr/log
```

----
[Open source ByJG](http://opensource.byjg.com)
