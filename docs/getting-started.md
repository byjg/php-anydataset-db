---
sidebar_position: 1
---

# Getting Started

## 1. Install the ByJG AnyDatasetDB Library

Install the library using Composer:

```bash
composer require byjg/anydataset-db
```

## 2. Connect to the database

Set up a database connection. ByJG AnyDatasetDB supports multiple databases, including MySQL, PostgreSQL, SQL Server,
Oracle and SQLite.

Example: Connecting to a MySQL database:

```php
<?php
require 'vendor/autoload.php';

use ByJG\AnyDataset\Db\Factory;

// Create a connection string
$connectionString = 'mysql://user:password@localhost/databasename';

// Create a DbDriver
$dbDriver = Factory::createDbDriver($connectionString);
```

## 3. Perform a query

Once your database connection is established, you can perform queries using the DbDriver object.

Example: Simple SELECT query:

```php
<?php
use ByJG\AnyDataset\Core\Row;
use ByJG\AnyDataset\Core\IteratorInterface;

// Define your SQL query
$sql = "SELECT * FROM your_table WHERE id = :id";

// Execute the query with parameters
$iterator = $dbDriver->getIterator($sql, [':id' => 1]);

// Fetch results
foreach ($iterator as $row) {
    /** @var Row $row */
    $data = $row->toArray();
    print_r($data);
}
```

## 4. Insert, Update, or Delete data

You can use the execute method for data manipulation operations.

Insert data:

```php
<?php
$sql = "INSERT INTO your_table (name, age) VALUES (:name, :age)";
$dbDriver->execute($sql, [':name' => 'John', ':age' => 30]);
```

Update data:

```php
<?php
$sql = "UPDATE your_table SET age = :age WHERE name = :name";
$dbDriver->execute($sql, [':age' => 31, ':name' => 'John']);
```

Delete data:

```php
<?php
$sql = "DELETE FROM your_table WHERE name = :name";
$dbDriver->execute($sql, [':name' => 'John']);
```

## 5. Close the connection (Optional)

You can explicitly close the connection when you're done:

```php
<?php
$dbDriver->disconnect();
```

## 6. Putting it all together

Here’s an example of querying data from a MySQL database using ByJG AnyDatasetDB:

```php
<?php
require 'vendor/autoload.php';

use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Core\Row;

// Create a connection to the database
$connectionString = 'mysql://user:password@localhost/databasename';
$dbDriver = Factory::createDbDriver($connectionString);

// Define and execute the query
$sql = "SELECT * FROM your_table WHERE age > :age";
$iterator = $dbDriver->getIterator($sql, [':age' => 25]);

// Loop through the results
foreach ($iterator as $row) {
    /** @var Row $row */
    $data = $row->toArray();
    print_r($data);
}

// Disconnect from the database
$dbDriver->disconnect();
```

## Conclusion

With ByJG AnyDatasetDB, querying a database is straightforward. The main steps involve connecting 
to the database, preparing SQL queries, and using getIterator() for SELECT queries or execute() 
for INSERT, UPDATE, or DELETE operations.
