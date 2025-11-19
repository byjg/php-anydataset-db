<?php

namespace TestDb;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\DatabaseRouter;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\Interfaces\DbDriverInterface;
use ByJG\AnyDataset\Db\Interfaces\SqlDialectInterface;
use Exception;
use PHPUnit\Framework\TestCase;

class DatabaseRouterlDbTest extends TestCase
{
    protected DatabaseRouter $route;
    protected DatabaseExecutor $executor;
    protected DbDriverInterface $masterDriver;
    protected DbDriverInterface $slave1Driver;
    protected DbDriverInterface $slave2Driver;
    protected DbDriverInterface $analyticsDriver;

    protected string $masterDb = '/tmp/master.db';
    protected string $slave1Db = '/tmp/slave1.db';
    protected string $slave2Db = '/tmp/slave2.db';
    protected string $analyticsDb = '/tmp/analytics.db';

    public function setUp(): void
    {
        // Create database drivers
        $this->masterDriver = Factory::getDbInstance("sqlite://$this->masterDb");
        $this->slave1Driver = Factory::getDbInstance("sqlite://$this->slave1Db");
        $this->slave2Driver = Factory::getDbInstance("sqlite://$this->slave2Db");
        $this->analyticsDriver = Factory::getDbInstance("sqlite://$this->analyticsDb");

        // Create the DatabaseRouter instance
        $this->route = new DatabaseRouter();

        // Set up routing
        $this->route
            ->addDriver('master', $this->masterDriver)
            ->addDriver('slaves', [$this->slave1Driver, $this->slave2Driver])
            ->addDriver('analytics', $this->analyticsDriver);

        // Define routing rules
        // IMPORTANT: More specific routes should come BEFORE general routes
        $this->route
            ->addRouteForTable('analytics', 'analytics')    // Analytics table goes to analytics db (must be first!)
            ->addRouteForWrite('master')                    // All writes go to master
            ->addRouteForRead('slaves');                    // All reads go to slaves (load balanced)

        // Create DatabaseExecutor wrapper around DatabaseRouter
        $this->executor = DatabaseExecutor::using($this->route);

        // Create tables and populate data
        $this->createDatabases();
        $this->populateData();
    }

    protected function createDatabases(): void
    {
        // Create users table in master
        DatabaseExecutor::using($this->masterDriver)->execute('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50),
                email VARCHAR(100)
            )
        ');

        // Create users table in slaves (same structure)
        DatabaseExecutor::using($this->slave1Driver)->execute('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50),
                email VARCHAR(100)
            )
        ');

        DatabaseExecutor::using($this->slave2Driver)->execute('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50),
                email VARCHAR(100)
            )
        ');

        // Create analytics table in analytics database
        DatabaseExecutor::using($this->analyticsDriver)->execute('
            CREATE TABLE IF NOT EXISTS analytics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event VARCHAR(50),
                count INTEGER
            )
        ');
    }

    protected function populateData(): void
    {
        // Populate master database
        DatabaseExecutor::using($this->masterDriver)->execute("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
        DatabaseExecutor::using($this->masterDriver)->execute("INSERT INTO users (name, email) VALUES ('Bob', 'bob@example.com')");

        // Populate slave1 (replicated data)
        DatabaseExecutor::using($this->slave1Driver)->execute("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
        DatabaseExecutor::using($this->slave1Driver)->execute("INSERT INTO users (name, email) VALUES ('Bob', 'bob@example.com')");

        // Populate slave2 (replicated data)
        DatabaseExecutor::using($this->slave2Driver)->execute("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
        DatabaseExecutor::using($this->slave2Driver)->execute("INSERT INTO users (name, email) VALUES ('Bob', 'bob@example.com')");

        // Populate analytics
        DatabaseExecutor::using($this->analyticsDriver)->execute("INSERT INTO analytics (event, count) VALUES ('page_view', 100)");
        DatabaseExecutor::using($this->analyticsDriver)->execute("INSERT INTO analytics (event, count) VALUES ('click', 50)");
    }

    public function tearDown(): void
    {
        // Clean up - drop tables and delete database files
        try {
            DatabaseExecutor::using($this->masterDriver)->execute('DROP TABLE IF EXISTS users');
            DatabaseExecutor::using($this->slave1Driver)->execute('DROP TABLE IF EXISTS users');
            DatabaseExecutor::using($this->slave2Driver)->execute('DROP TABLE IF EXISTS users');
            DatabaseExecutor::using($this->analyticsDriver)->execute('DROP TABLE IF EXISTS analytics');
        } catch (Exception $e) {
            // Ignore errors during cleanup
        }

        if (file_exists($this->masterDb)) unlink($this->masterDb);
        if (file_exists($this->slave1Db)) unlink($this->slave1Db);
        if (file_exists($this->slave2Db)) unlink($this->slave2Db);
        if (file_exists($this->analyticsDb)) unlink($this->analyticsDb);
    }

    public function testReadFromSlaves(): void
    {
        // Read query should go to one of the slaves
        $result = $this->executor->getIterator('SELECT * FROM users')->toArray();

        // Verify data was retrieved
        $this->assertCount(2, $result);
        $this->assertEquals('Alice', $result[0]['name']);
        $this->assertEquals('Bob', $result[1]['name']);
    }

    public function testWriteToMaster(): void
    {
        // Write should go to master
        $id = $this->executor->executeAndGetId("INSERT INTO users (name, email) VALUES ('Charlie', 'charlie@example.com')");
        $this->assertEquals(3, $id);

        // Verify it was inserted in master
        $result = DatabaseExecutor::using($this->masterDriver)->getIterator('SELECT * FROM users WHERE id = 3')->toArray();
        $this->assertCount(1, $result);
        $this->assertEquals('Charlie', $result[0]['name']);

        // Verify it was NOT inserted in slaves (in real scenario, replication would handle this)
        $resultSlave1 = DatabaseExecutor::using($this->slave1Driver)->getIterator('SELECT * FROM users WHERE id = 3')->toArray();
        $this->assertCount(0, $resultSlave1);
    }

    public function testUpdateGoesToMaster(): void
    {
        // Update should go to master
        $this->executor->execute("UPDATE users SET email = 'alice.updated@example.com' WHERE id = 1");

        // Verify it was updated in master
        $result = DatabaseExecutor::using($this->masterDriver)->getIterator('SELECT email FROM users WHERE id = 1')->toArray();
        $this->assertEquals('alice.updated@example.com', $result[0]['email']);

        // Verify it was NOT updated in slaves
        $resultSlave1 = DatabaseExecutor::using($this->slave1Driver)->getIterator('SELECT email FROM users WHERE id = 1')->toArray();
        $this->assertEquals('alice@example.com', $resultSlave1[0]['email']);
    }

    public function testDeleteGoesToMaster(): void
    {
        // Delete should go to master
        $this->executor->execute("DELETE FROM users WHERE id = 1");

        // Verify it was deleted from master
        $result = DatabaseExecutor::using($this->masterDriver)->getIterator('SELECT * FROM users WHERE id = 1')->toArray();
        $this->assertCount(0, $result);

        // Verify it still exists in slaves
        $resultSlave1 = DatabaseExecutor::using($this->slave1Driver)->getIterator('SELECT * FROM users WHERE id = 1')->toArray();
        $this->assertCount(1, $resultSlave1);
    }

    public function testAnalyticsTableRouting(): void
    {
        // Queries to analytics table should go to analytics database
        $result = $this->executor->getIterator('SELECT * FROM analytics')->toArray();

        $this->assertCount(2, $result);
        $this->assertEquals('page_view', $result[0]['event']);
        $this->assertEquals(100, $result[0]['count']);
    }

    public function testAnalyticsTableWrite(): void
    {
        // Insert into analytics should go to analytics database
        $this->executor->execute("INSERT INTO analytics (event, count) VALUES ('signup', 25)");

        // Verify it was inserted in analytics database
        $result = DatabaseExecutor::using($this->analyticsDriver)->getIterator('SELECT * FROM analytics WHERE event = \'signup\'')->toArray();
        $this->assertCount(1, $result);
        $this->assertEquals(25, $result[0]['count']);
    }

    public function testLoadBalancingBetweenSlaves(): void
    {
        // Execute multiple reads and track which databases were hit
        $hitCounts = ['slave1' => 0, 'slave2' => 0];

        // Add a unique marker to each slave to identify which was used
        DatabaseExecutor::using($this->slave1Driver)->execute("INSERT INTO users (id, name, email) VALUES (999, 'SLAVE1_MARKER', 'marker@slave1.com')");
        DatabaseExecutor::using($this->slave2Driver)->execute("INSERT INTO users (id, name, email) VALUES (999, 'SLAVE2_MARKER', 'marker@slave2.com')");

        // Execute many reads to see load balancing in action
        for ($i = 0; $i < 20; $i++) {
            $result = $this->executor->getIterator('SELECT name FROM users WHERE id = 999')->toArray();

            if ($result[0]['name'] === 'SLAVE1_MARKER') {
                $hitCounts['slave1']++;
            } elseif ($result[0]['name'] === 'SLAVE2_MARKER') {
                $hitCounts['slave2']++;
            }
        }

        // Both slaves should have been hit at least once (statistically very likely with 20 requests)
        // Note: This is probabilistic, but with 20 requests it's extremely unlikely both won't be hit
        $this->assertGreaterThan(0, $hitCounts['slave1'], 'Slave1 should have been hit at least once');
        $this->assertGreaterThan(0, $hitCounts['slave2'], 'Slave2 should have been hit at least once');

        // Total should be 20
        $this->assertEquals(20, $hitCounts['slave1'] + $hitCounts['slave2']);
    }

    public function testGetScalarWithRouting(): void
    {
        // Test getScalar with routing
        $count = $this->executor->getScalar('SELECT COUNT(*) FROM users');
        $this->assertEquals(2, $count);
    }

    public function testTransactionOnMaster(): void
    {
        // Execute a write query first to establish the master route
        // This sets the lastMatchedExecutor which is needed for transaction management
        $this->executor->execute("INSERT INTO users (name, email) VALUES ('PreTransaction', 'pre@example.com')");

        // Now we can begin a transaction on the master
        $this->executor->beginTransaction();

        try {
            $this->executor->execute("INSERT INTO users (name, email) VALUES ('Dave', 'dave@example.com')");
            $this->executor->execute("INSERT INTO users (name, email) VALUES ('Eve', 'eve@example.com')");

            $this->executor->commitTransaction();
        } catch (Exception $e) {
            $this->executor->rollbackTransaction();
            throw $e;
        }

        // Verify all were inserted in master
        $result = DatabaseExecutor::using($this->masterDriver)->getIterator('SELECT * FROM users WHERE name IN (\'Dave\', \'Eve\', \'PreTransaction\')')->toArray();
        $this->assertCount(3, $result);
    }

    public function testGetAllFieldsWithRouting(): void
    {
        // getAllFields should work through routing
        $fields = $this->executor->getAllFields('users');

        $this->assertContains('id', $fields);
        $this->assertContains('name', $fields);
        $this->assertContains('email', $fields);
    }

    public function testGetDbHelperThroughRoute(): void
    {
        // After a query, we should be able to get the helper from the last matched driver
        $this->executor->getIterator('SELECT * FROM users');

        // Call getDbHelper() on the route (DbDriverInterface)
        $helper = $this->route->getSqlDialect();
        $this->assertInstanceOf(SqlDialectInterface::class, $helper);
    }

    public function testConnectionMethodsThroughRoute(): void
    {
        // Execute a query first to establish a connection and set lastMatchedDriver
        $this->executor->getIterator('SELECT * FROM users');

        // Now connection methods should work on the route
        $this->assertTrue($this->route->isConnected());

        // Reconnect should work
        $this->route->reconnect(true);
        $this->assertTrue($this->route->isConnected());

        // Query should still work after reconnect
        $result = $this->executor->getIterator('SELECT * FROM users')->toArray();
        $this->assertCount(2, $result);
    }
}
