---
sidebar_position: 14
---

# Pre-Fetch Records

By default, records are fetched from the database one at a time as you iterate over them using methods like
`moveNext()`,
`toArray()`, or a `foreach` loop. However, AnyDataset-DB allows you to pre-fetch a specified number of records into
memory
before starting the iteration, which can improve performance in certain scenarios.

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

The pre-fetch functionality is implemented in the `PreFetchTrait` trait, which is used by iterator classes like
`DbIterator` and `Oci8Iterator`. The implementation provides:

1. A row buffer that stores pre-fetched records in memory
2. Methods to control the pre-fetch behavior
3. Automatic cursor management to release database resources when done

### Key Components

The trait contains these key components:

```php
trait PreFetchTrait
{
    protected int $currentRow = 0;       // Tracks the current row for iterator position
    protected int $preFetchRows = 0;     // Number of rows to pre-fetch
    protected array $rowBuffer = [];     // Buffer holding pre-fetched rows
    
    // Methods for managing pre-fetching
    protected function initPreFetch(int $preFetch = 0): void { /* ... */ }
    protected function preFetch(): bool { /* ... */ }
    protected function isPreFetchBufferFull(): bool { /* ... */ }
    
    // Accessor methods
    public function getPreFetchRows(): int { /* ... */ }
    public function setPreFetchRows(int $preFetchRows): void { /* ... */ }
    public function getPreFetchBufferSize(): int { /* ... */ }
    
    // Iterator implementation
    public function current(): ?RowInterface { /* ... */ }
    public function next(): void { /* ... */ }
    public function valid(): bool { /* ... */ }
    public function key(): int { /* ... */ }
}
```

### Pre-Fetch Process

When you specify a pre-fetch value, the following happens:

1. `initPreFetch()` initializes the buffer and triggers the initial pre-fetch
2. `preFetch()` fetches records from the database until:
   - The buffer contains the requested number of records
   - There are no more records to fetch
3. During iteration:
   - `current()` returns the first record in the buffer
   - `next()` removes the first record from the buffer and calls `preFetch()` to fetch more if needed
   - `valid()` checks if there are more records in the buffer or if more can be fetched

### Cursor Management

The pre-fetch mechanism automatically manages the database cursor:

1. Records are fetched from the cursor when needed
2. When all records have been fetched, the cursor is automatically released via `releaseCursor()`
3. If the iterator is destroyed, any open cursor is released in the destructor

You can check if a cursor is still open using:

```php
$isOpen = $iterator->isCursorOpen();
```

## Controlling Pre-Fetch Behavior

You can adjust pre-fetch settings during iteration:

```php
// Get the current pre-fetch count
$preFetchCount = $iterator->getPreFetchRows();

// Change the pre-fetch count
$iterator->setPreFetchRows(200);

// Check the current buffer size
$bufferSize = $iterator->getPreFetchBufferSize();
```

## Use Cases for Pre-Fetch

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

## When Not to Use Pre-Fetch

Pre-fetching may lead to memory issues in certain scenarios. Avoid using pre-fetch if:

* Records contain too many fields (e.g., dozens of columns)
* Records include large fields, such as blobs or extensive text data
* You're dealing with a very large result set and memory is limited

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

## How It Works: An Example

When you call `getIterator` with a pre-fetch value, the specified number of records is fetched
from the database and stored in memory. During iteration, records are retrieved from memory,
and new batches are fetched as needed.

Example:

* You have a table with 60 records and set the pre-fetch count to 50.
* Upon obtaining the iterator, the first 50 records are fetched and stored in the buffer.
* As you iterate, the first record is returned from the buffer and removed.
* After processing 49 records (leaving just one in the buffer), the next pre-fetch operation retrieves the remaining 10
  records.
* After the 60th record, the database cursor is automatically closed as there are no more records to fetch.

## Memory Optimization

The pre-fetch mechanism optimizes memory usage by:

1. Only storing the pre-fetch count number of records in memory at once
2. Removing records from the buffer after they've been processed
3. Automatically closing the database cursor when all records have been fetched

This ensures efficient memory usage while still providing performance benefits.


