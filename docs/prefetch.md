---
sidebar_position: 13
---

# Pre-Fetch Records

By default, records are fetched from the database as you iterate over them using methods like `moveNext()`,
`toArray()`, or `foreach`.

It is possible to pre-fetch a specified number of records before starting the iteration.

## Basic Usage

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\SqlStatement;

// Using SqlStatement
$sql = new SqlStatement("select * from table where field = :param");

// Pre-fetch 100 records
$iterator = $sql->getIterator($dbDriver, ['param' => 'value'], preFetch: 100);

// Or directly with DbDriver
$iterator = $dbDriver->getIterator(
    "select * from table where field = :param", 
    ['param' => 'value'], 
    preFetch: 100
);
```

## Implementation Details

The pre-fetch functionality is implemented in the `PreFetchTrait` trait, which is used by the `DbIterator` class. When
you specify a pre-fetch value, the following happens:

1. The `initPreFetch` method initializes the pre-fetch buffer.
2. The `preFetch` method fetches records from the database and stores them in the buffer until the buffer is full or
   there are no more records.
3. The `moveNext` method retrieves records from the buffer and triggers additional pre-fetching when needed.
4. The `hasNext` method checks if there are more records in the buffer or if more records can be fetched from the
   database.

You can check the current pre-fetch buffer size with:

```php
$bufferSize = $iterator->getPreFetchBufferSize();
```

And you can get or set the pre-fetch count with:

```php
$preFetchCount = $iterator->getPreFetchRows();
$iterator->setPreFetchRows(200); // Change the pre-fetch count
```

## Use cases for pre-fetch:

### Small tables with a few records

If your table contains a small number of records, it is more efficient to fetch all records at once
and store them in memory during iteration. If the pre-fetch count exceeds the number of available records,
all records will be fetched and stored, releasing the database connection.
This allows iteration from memory, enhancing performance.

Example:

```php
<?php
// For small tables, pre-fetch all records (e.g., 1000)
$iterator = $dbDriver->getIterator("SELECT * FROM small_table", preFetch: 1000);

// Process records (database connection is released after pre-fetching)
foreach ($iterator as $row) {
    // Process each row
    echo $row->get('column_name') . "\n";
}
```

### Long processing time

For operations with long processing times between record iterations, pre-fetching records into memory
releases database resources earlier, improving efficiency.

Example:

```php
<?php
// Pre-fetch records to release database connection during processing
$iterator = $dbDriver->getIterator("SELECT * FROM table", preFetch: 50);

foreach ($iterator as $row) {
    // Perform time-consuming operations
    processComplexData($row);
    
    // The database connection is released after pre-fetching,
    // allowing other processes to use it during this time
}
```

## When not to use pre-fetch

Pre-fetching may lead to memory issues in certain scenarios. Avoid using pre-fetch if:

* Records contain too many fields (e.g., dozens of columns).
* Records include large fields, such as blobs or extensive text data.
* You're dealing with a very large result set and memory is limited.

Example of when not to use pre-fetch:

```php
<?php
// For large tables with many columns or BLOB data, avoid pre-fetch
$iterator = $dbDriver->getIterator(
    "SELECT * FROM large_table_with_blobs", 
    preFetch: 0  // Explicitly set to 0 to disable pre-fetch
);

foreach ($iterator as $row) {
    // Process each row one at a time
    processRow($row);
}
```

## How it works

When you call `getIterator` with a pre-fetch value, the specified number of records is fetched
from the database and stored in memory. During iteration, records are retrieved from memory,
and new batches are fetched as needed.

Example:

* You have a table with 60 records and set the pre-fetch count to 50.
* Upon obtaining the iterator, the first 50 records are fetched from the database and stored in memory.
* Each record is retrieved from memory during iteration, while the next batch of records is fetched from the database as
  needed.
* After the 60th record, the database cursor is closed, and any remaining records are fetched directly from memory.

## Memory Management

The pre-fetch mechanism automatically manages memory by:

1. Releasing records from the buffer after they've been processed
2. Closing the database cursor when all records have been fetched
3. Releasing the cursor when the iterator is destroyed

This ensures efficient memory usage while still providing the performance benefits of pre-fetching.


