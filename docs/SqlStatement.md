# SqlStatement

The `SqlStatement` class provides a standardized way to work with SQL queries in the AnyDataset-DB library. It offers a
clean API for building, parameterizing, executing, and caching SQL operations.

## Basic Usage

### Creating SQL Statements

```php
use ByJG\AnyDataset\Db\SqlStatement;

// Create a simple SQL statement
$sql = new SqlStatement('SELECT * FROM users');

// Create a SQL statement with parameters
$sql = new SqlStatement(
    'SELECT * FROM users WHERE status = :status AND created_at > :date',
    [
        'status' => 'active',
        'date' => '2023-01-01'
    ]
);

// Alternative static factory method
$sql = SqlStatement::from('SELECT * FROM table WHERE id = :id', ['id' => 123]);
```

### Executing SQL Statements

```php
// Get a database driver
$dbDriver = Factory::getDbRelationalInstance($connectionUri);

// Execute and get results as an iterator
$iterator = $sql->getIterator($dbDriver);
foreach ($iterator as $row) {
    // Process each row
}

// Get a single scalar value
$count = $sql->getScalar($dbDriver);

// Execute a statement (for INSERT, UPDATE, DELETE)
$sql->execute($dbDriver);
```

## Parameters

The `SqlStatement` class now supports storing parameters as part of the statement, which simplifies the API and improves
code clarity.

### Working with Parameters

```php
// 1. Parameters can be set during creation
$sql = new SqlStatement(
    'SELECT * FROM users WHERE dept_id = :deptId',
    ['deptId' => 5]
);

// 2. Parameters can be modified after creation
$sql->withParams(['deptId' => 10]);

// 3. Parameters can be overridden during execution
$iterator = $sql->getIterator($dbDriver, ['deptId' => 15]);

// 4. Parameters are accessible
$params = $sql->getParams();
```

### Parameter Handling

- Parameters are bound to placeholders in the SQL query (e.g., `:paramName`).
- If a parameter is provided during execution, it will override any stored parameters.
- When no parameters are provided during execution, the stored parameters are used.
- The parameter binding is handled by the database driver, protecting against SQL injection.

## Caching

SqlStatement supports caching query results to improve performance for frequently executed queries.

```php
// Enable caching for a query
$sql->withCache($cacheImplementation, 'users_list', 300); // Cache for 5 minutes

// Disable caching
$sql->withoutCache();
```

When caching is enabled, the parameters are taken into account when creating the cache key, so different parameter
values will result in different cached results.

## Advanced Usage

### Directly Preparing Statements

For more control, you can prepare a statement and then execute it:

```php
$statement = $sql->prepare($dbDriver);
$dbDriver->executeCursor($statement);
```

### With Entity Mapping

```php
// Get results as User objects
$iterator = $sql->getIterator(
    $dbDriver, 
    ['status' => 'active'], 
    0, 
    User::class
);

foreach ($iterator as $user) {
    // $user is an instance of User
    echo $user->getName();
}
```

## Benefits of Parameter Integration

1. **Cleaner API**: The SQL and its parameters are kept together, making the code more readable.
2. **Reusability**: A SqlStatement with parameters can be defined once and reused with different parameter values.
3. **Consistency**: Parameters are handled consistently across different database drivers.
4. **Performance**: Parameter binding is optimized for the specific database driver.
5. **Security**: Parameter binding protects against SQL injection attacks.

## Example

```php
$userQuery = new SqlStatement(
    'SELECT * FROM users WHERE status = :status AND role = :role',
    ['status' => 'active']
);

// Get all active admins
$admins = $userQuery->withParams(['status' => 'active', 'role' => 'admin'])
    ->getIterator($dbDriver);

// Get all active users (overriding the role)
$users = $userQuery->getIterator($dbDriver, ['status' => 'active', 'role' => 'user']);
``` 