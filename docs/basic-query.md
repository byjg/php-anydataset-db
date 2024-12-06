---
sidebar_position: 2
---

# Basics

## Basic Query

```php
<?php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbInstance('mysql://username:password@host/database');
$iterator = $dbDriver->getIterator('select * from table where field = :param', ['param' => 'value']);
foreach ($iterator as $row) {
    // Do Something
    // $row->getField('field');
}
```

## Updating in Relational databases

```php
<?php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbInstance('mysql://username:password@host/database');
$dbDriver->execute(
    'update table set other = :value where field = :param',
    [
        'value' => 'othervalue',
        'param' => 'value of param'
    ]
);
```

## Inserting and Get Id

```php
<?php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbInstance('mysql://username:password@host/database');
$id = $dbDriver->executeAndGetId(
    'insert into table (field1, field2) values (:param1, :param2)',
    [
        'param1' => 'value1',
        'param2' => 'value2'
    ]
);
```

## Database Transaction

```php
<?php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbInstance('mysql://username:password@host/database');
$dbDriver->beginTransaction(\ByJG\AnyDataset\Db\IsolationLevelEnum::SERIALIZABLE);

// ... Do your queries

$dbDriver->commitTransaction(); // or rollbackTransaction()
```
