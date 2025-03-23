---
sidebar_position: 9
---

# Entity Mapping

Entity mapping is a feature that allows you to map database query results directly to PHP objects instead of generic
array structures. This provides a more convenient and type-safe way to work with your data.

## Basic Usage

To use entity mapping, you need to:

1. Define a PHP class that represents your entity
2. Pass the class name to the `getIterator()` method

```php
<?php
// 1. Define your entity class
class User {
    public int $id;
    public string $name;
    public string $email;
    public bool $active;
}

// 2. Get a database connection
$conn = \ByJG\AnyDataset\Db\Factory::getDbInstance("mysql://root:password@localhost/myschema");

// 3. Query with entity mapping by passing the entity class name
$iterator = $conn->getIterator(
    "SELECT * FROM users WHERE active = :active", 
    [':active' => true],
    null,               // cache
    60,                 // cache TTL
    0,                  // prefetch
    User::class         // Entity class name
);

// 4. Iterate through User objects
foreach ($iterator as $row) {
    // Get the mapped entity object
    $user = $row->entity();
    
    // Now $user is a fully populated User object
    echo "User: {$user->name} ({$user->email})\n";
    
    // You can work with the properties directly
    if ($user->active) {
        // Do something with active users
    }
}
```

## Property Mapping

The entity mapping feature maps database column names directly to object properties. The mapping is case-insensitive, so
a column named `user_id` would map to a property named `userId`, `USER_ID`, or `user_id`.

For best results:

- Make sure your entity class has properties that match your database column names
- Properties should be declared as `public` to allow direct assignment
- Use type declarations for better type safety

## Custom Property Transformation

In some cases, you might want to customize how database field values are mapped to your entity properties. For this, you
can use the `entityTransformer` parameter to provide a custom transformation function:

```php
<?php
// Define your entity class
class Product {
    public int $id;
    public string $name;
    public float $price;
    public string $currency;
    public float $priceInUSD;
}

// Create a custom transformer function
$transformer = function ($sourceField) {
    // Convert snake_case to camelCase
    if ($sourceField === 'product_id') {
        return 'id';
    }
    
    // For all other fields, use the original name
    return $sourceField;
};

// Pass the transformer along with the entity class
$iterator = $conn->getIterator(
    "SELECT 
        product_id, 
        name, 
        price, 
        currency
     FROM products", 
    [],
    null,               // cache
    60,                 // cache TTL
    0,                  // prefetch
    Product::class,     // Entity class name
    $transformer        // Custom transformer function
);

// Iterate through transformed Product objects
foreach ($iterator as $row) {
    $product = $row->entity();
    // Now product_id field was mapped to id property
}
```

You can also use the transformer to perform more complex transformations, such as combining multiple fields:

```php
<?php
// Create transformer that calculates values on the fly
$priceTransformer = function ($sourceField, $targetField, $value) {
    // Convert price to USD based on currency
    if ($sourceField === 'price' && !empty($this->currency)) {
        // Calculate USD price based on currency
        $this->priceInUSD = $value * getCurrencyRate($this->currency);
    }
    
    return $value;
};
```

## Benefits of Entity Mapping

### 1. Type Safety

With entity mapping, you get the benefit of PHP's type system:

```php
class Product {
    public int $id;
    public string $name;
    public float $price;
    public ?string $description;
}

// The price will be a float, not a string
$product = $iterator->current()->entity();
$total = $product->price * 1.1; // Type-safe calculation
```

### 2. IDE Auto-completion

Your IDE can provide code completion for entity properties:

```php
$user = $iterator->current()->entity();
$user->name // IDE suggests available properties like name, email, etc.
```

### 3. Improved Code Organization

Entity mapping helps you organize your code according to domain objects:

```php
// User entity with business logic
class User {
    public int $id;
    public string $name;
    public string $email;
    
    public function getFullName(): string {
        return "{$this->name} <{$this->email}>";
    }
    
    public function isValidEmail(): bool {
        return filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

// Using the entity methods
$user = $iterator->current()->entity();
echo $user->getFullName();
if (!$user->isValidEmail()) {
    // Handle invalid email
}
```

### 4. Clean Data Access Layer

Entity mapping creates a clear separation between your database access code and business logic:

```php
// Data access layer
function getUserById(DbDriverInterface $db, int $userId): ?User {
    $iterator = $db->getIterator(
        "SELECT * FROM users WHERE id = :id",
        [':id' => $userId],
        null, 60, 0, User::class
    );
    
    if ($iterator->valid()) {
        return $iterator->current()->entity();
    }
    
    return null;
}

// Business logic
$user = getUserById($dbConn, 123);
if ($user !== null) {
    // Process user
}
```

## Handling JOINs and Complex Queries

For queries that join multiple tables, you can still map the results to entities. Just ensure your entity class has
properties that match the column names in your query result:

```php
// Query joining users and orders tables
$sql = "
    SELECT 
        u.id as user_id, 
        u.name as user_name, 
        u.email,
        o.id as order_id, 
        o.total
    FROM users u
    JOIN orders o ON u.id = o.user_id
    WHERE o.status = :status
";

class UserOrder {
    public int $user_id;
    public string $user_name;
    public string $email;
    public int $order_id;
    public float $total;
}

$iterator = $conn->getIterator($sql, [':status' => 'completed'], null, 60, 0, UserOrder::class);
```

## Combining with Other Features

Entity mapping works seamlessly with other AnyDataset-DB features:

### With Cache

```php
// Cache results with entity mapping
$cacheInstance = new \Your\Cache\Implementation();
$iterator = $conn->getIterator(
    "SELECT * FROM users", 
    null,
    $cacheInstance,
    300, // 5 minutes
    0,
    User::class
);
```

### With PreFetch

```php
// Prefetch 100 records at a time with entity mapping
$iterator = $conn->getIterator(
    "SELECT * FROM large_table", 
    null,
    null,
    60,
    100, // Prefetch 100 records
    LargeEntity::class
);
```

## Performance Considerations

Entity mapping adds a small overhead compared to working with raw arrays, but the benefits in code organization and
maintainability often outweigh this cost. For extremely performance-sensitive operations with large result sets, you
might consider:

1. Using the raw iterator without entity mapping
2. Limiting the number of records returned from the database
3. Using the PreFetch feature to optimize memory usage 