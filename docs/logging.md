---
sidebar_position: 17
---

# Logging

AnyDataset-DB provides built-in logging capabilities through PSR-3 compliant loggers. This allows you to track and debug
database operations by logging SQL queries, parameters, and other relevant information.

## Enabling Logging

To enable logging in your database driver, you need to provide a PSR-3 compliant logger instance using the
`enableLogger()` method:

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create a logger instance (using Monolog as an example)
$logger = new Logger('database');
$logger->pushHandler(new StreamHandler('database.log', Logger::DEBUG));

// Get a database driver instance
$dbDriver = Factory::getDbInstance('mysql://user:password@host/database');

// Enable logging
$dbDriver->enableLogger($logger);
```

## What Gets Logged

The database driver automatically logs the following information:

- SQL queries being executed
- Query parameters
- Transaction operations (begin, commit, rollback)
- Connection events

## Log Levels

The driver uses the following log levels:

- `debug`: For SQL queries, parameters, and general database operations
- `info`: For connection events and transaction operations
- `error`: For database errors and exceptions

## Example Usage

Here's a complete example showing how to set up logging with Monolog:

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

// Create a logger instance
$logger = new Logger('database');

// Create a rotating file handler (keeps logs for 30 days)
$handler = new RotatingFileHandler(
    'logs/database.log',
    30,
    Logger::DEBUG
);

// Create a line formatter
$formatter = new LineFormatter(
    "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
    "Y-m-d H:i:s"
);

// Set the formatter
$handler->setFormatter($formatter);

// Add the handler to the logger
$logger->pushHandler($handler);

// Get database instance
$dbDriver = Factory::getDbInstance('mysql://user:password@host/database');

// Enable logging
$dbDriver->enableLogger($logger);

// Now all database operations will be logged
$dbDriver->execute("INSERT INTO users (name, email) VALUES (?, ?)", ['John Doe', 'john@example.com']);
```

## Log Output Example

When logging is enabled, you'll see output like this in your log file:

```
[2024-03-25 10:15:30] database.DEBUG: SQL: INSERT INTO users (name, email) VALUES (?, ?) Params: ["John Doe","john@example.com"] {"sql":"INSERT INTO users (name, email) VALUES (?, ?)","params":["John Doe","john@example.com"]}
[2024-03-25 10:15:30] database.DEBUG: SQL: Begin transaction {"operation":"begin_transaction"}
[2024-03-25 10:15:30] database.DEBUG: SQL: Commit transaction {"operation":"commit_transaction"}
```

## Available Logging Libraries

You can use any PSR-3 compliant logging library. Here are some popular options:

- [Monolog](https://github.com/Seldaek/monolog)
- [Psr\Log\LoggerInterface](https://github.com/php-fig/log)
- [Symfony Logger](https://symfony.com/doc/current/logging.html)

## Best Practices

1. Use appropriate log levels for different types of information
2. Implement log rotation to manage log file sizes
3. Consider using structured logging for better log analysis
4. Be careful not to log sensitive information (passwords, personal data)
5. Use different log handlers for development and production environments 