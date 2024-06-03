# Using IteratorFilter

`IteratorFilter` is a class that helps you to create a filter to be used in the Iterator. 
It is standard across all AnyDataset implementations.

## Basic Usage

```php
<?php
// Create the IteratorFilter instance
$filter = new \ByJG\AnyDataset\Core\IteratorFilter();
$filter->addRelation('field', \ByJG\AnyDataset\Enum\Relation::EQUAL, 10);

// Generate the SQL
$param = [];
$formatter = new \ByJG\AnyDataset\Db\IteratorFilterSqlFormatter();
$sql = $formatter->format(
    $filter->getRawFilters(),
    'mytable',
    $param,
    'field1, field2'
);

// Execute the Query
$iterator = $db->getIterator($sql, $param);
```

## Using IteratorFilter with Literal values

Sometimes you need an argument as a Literal value like a function or an explicit conversion.

In this case you have to create a class that expose the "__toString()" method

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
$filter->addRelation('field', \ByJG\AnyDataset\Core\Enum\Relation::EQUAL, $literal);
```
