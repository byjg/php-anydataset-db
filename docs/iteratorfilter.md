---
sidebar_position: 8
---

# Using IteratorFilter

`IteratorFilter` is a class that simplifies creating filters for use with an `Iterator`.
It is a standard feature across all AnyDataset implementations.

## Basic Usage

```php
<?php
// Create the IteratorFilter instance
$filter = new \ByJG\AnyDataset\Core\IteratorFilter();
$filter->addRelation('field', \ByJG\AnyDataset\Core\Enum\Relation::Equal, 10);

// Generate the SQL
$param = [];
$formatter = new \ByJG\AnyDataset\Db\IteratorFilterSqlFormatter('mytable', 'field1, field2');
$sql = $filter->format($formatter, $param);
// $sql: select field1, field2 from mytable where field = :field

// Execute the Query
$iterator = \ByJG\AnyDataset\Db\DatabaseExecutor::using($dbDriver)->getIterator($sql, $param);
```

## Using IteratorFilter with Literal values

Sometimes, you may need to use an argument as a literal value, such as a function or an explicit conversion.

In such cases, you need to create a class that implements the `__toString()` method.
This method allows the literal value to be properly represented and used in the filter.

```php
<?php

// The class with the "__toString()" exposed
class MyLiteral
{
    //...
    
    public function __toString() {
        return "cast('10' as integer)";
    }
}

// Create the literal instance
$literal = new MyLiteral();

// Using the IteratorFilter:
$filter = new \ByJG\AnyDataset\Core\IteratorFilter();
$filter->addRelation('field', \ByJG\AnyDataset\Core\Enum\Relation::Equal, $literal);
```
