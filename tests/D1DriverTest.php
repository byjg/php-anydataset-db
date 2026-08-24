<?php

namespace Test;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\DbD1Driver;
use ByJG\AnyDataset\Db\DbPdoDriver;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\SqlDialect\D1Dialect;
use ByJG\AnyDataset\Db\SqlStatement;
use ByJG\Util\Uri;
use ByJG\WebRequest\HttpClient;
use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\Helpers\D1FakeClient;
use Test\Models\Dogs;

/**
 * Unit tests for the Cloudflare D1 driver. Every HTTP round trip goes through D1FakeClient, so the
 * suite runs without credentials and without network access.
 */
class D1DriverTest extends TestCase
{
    protected const string URI = 'd1://my-account:my-token@api.cloudflare.com/db-uuid-1234';

    protected D1FakeClient $client;

    public function setUp(): void
    {
        $this->client = new D1FakeClient();
    }

    protected function createDriver(string $uri = self::URI): DbD1Driver
    {
        $driver = new DbD1Driver(new Uri($uri));
        $driver->setHttpClient($this->client);

        return $driver;
    }

    protected function createExecutor(string $uri = self::URI): DatabaseExecutor
    {
        return DatabaseExecutor::using($this->createDriver($uri));
    }

    // ------------------------------------------------------------------ registration and the URI

    public function testFactoryResolvesTheD1Scheme(): void
    {
        $driver = Factory::getDbInstance(self::URI);

        $this->assertInstanceOf(DbD1Driver::class, $driver);
        $this->assertEquals(['d1'], DbD1Driver::schema());
        $this->assertInstanceOf(D1Dialect::class, $driver->getSqlDialect());
    }

    public function testUriIsPreservedForRoundTrip(): void
    {
        $driver = $this->createDriver();

        $this->assertEquals(self::URI, (string)$driver->getUri());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function dataProviderIncompleteUri(): array
    {
        return [
            'no token' => ['d1://my-account@api.cloudflare.com/db-uuid'],
            'no account' => ['d1://:my-token@api.cloudflare.com/db-uuid'],
            'no database' => ['d1://my-account:my-token@api.cloudflare.com'],
        ];
    }

    #[DataProvider('dataProviderIncompleteUri')]
    public function testIncompleteUriIsRejected(string $uri): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('d1://{account_id}:{api_token}@api.cloudflare.com/{database_id}');

        new DbD1Driver(new Uri($uri));
    }

    // ------------------------------------------------------------------------- the HTTP request

    public function testRequestTargetsTheD1QueryEndpoint(): void
    {
        $this->client->queueSuccess();
        $this->createExecutor()->execute('DELETE FROM Dogs');

        $request = $this->client->getLastRequest();

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals(
            'https://api.cloudflare.com/client/v4/accounts/my-account/d1/database/db-uuid-1234/query',
            (string)$request->getUri()
        );
        $this->assertEquals('Bearer my-token', $request->getHeaderLine('Authorization'));
        $this->assertEquals('application/json', $request->getHeaderLine('Content-Type'));
    }

    public function testEndpointHostAndBasePathAreOverridable(): void
    {
        $this->client->queueSuccess();
        $uri = 'd1://acc:tok@localhost:8787/dbid?apischeme=http&basepath=/mock/v1';
        $this->createExecutor($uri)->execute('DELETE FROM Dogs');

        $this->assertEquals(
            'http://localhost:8787/mock/v1/accounts/acc/d1/database/dbid/query',
            (string)$this->client->getLastRequest()->getUri()
        );
    }

    public function testRequestBodyCarriesSqlAndPositionalParams(): void
    {
        $this->client->queueSuccess();
        $this->createExecutor()->execute(
            'INSERT INTO Dogs (Breed, Name) VALUES (:breed, :name)',
            ['breed' => 'Mutt', 'name' => 'Spyke']
        );

        $this->assertEquals(
            [
                'sql' => 'INSERT INTO Dogs (Breed, Name) VALUES (?, ?)',
                'params' => ['Mutt', 'Spyke'],
            ],
            $this->client->getLastBody()
        );
    }

    // ------------------------------------------------------------------------ parameter binding

    public function testParameterUsedTwiceBindsItsValueTwice(): void
    {
        $this->client->queueSuccess();
        $this->createExecutor()->execute(
            'SELECT * FROM Dogs WHERE Name = :name OR Breed = :name',
            ['name' => 'Lola']
        );

        $body = $this->client->getLastBody();

        $this->assertEquals('SELECT * FROM Dogs WHERE Name = ? OR Breed = ?', $body['sql']);
        $this->assertEquals(['Lola', 'Lola'], $body['params']);
    }

    public function testUndefinedParameterBecomesNullLiteral(): void
    {
        $this->client->queueSuccess();
        $this->createExecutor()->execute(
            'SELECT * FROM Dogs WHERE Name = :name AND Age = :age',
            ['name' => 'Lola']
        );

        $body = $this->client->getLastBody();

        $this->assertEquals('SELECT * FROM Dogs WHERE Name = ? AND Age = null', $body['sql']);
        $this->assertEquals(['Lola'], $body['params']);
    }

    public function testPlaceholderInsideQuotedLiteralIsNotBound(): void
    {
        $this->client->queueSuccess();
        $this->createExecutor()->execute(
            "SELECT * FROM Dogs WHERE Name = ':name' OR Breed = :breed",
            ['name' => 'ignored', 'breed' => 'Pincher']
        );

        $body = $this->client->getLastBody();

        $this->assertEquals("SELECT * FROM Dogs WHERE Name = ':name' OR Breed = ?", $body['sql']);
        $this->assertEquals(['Pincher'], $body['params']);
    }

    public function testDoubleColonCastIsNotTreatedAsPlaceholder(): void
    {
        $this->client->queueSuccess();
        $this->createExecutor()->execute('SELECT Age::text FROM Dogs WHERE Id = :id', ['id' => 3]);

        $body = $this->client->getLastBody();

        $this->assertEquals('SELECT Age::text FROM Dogs WHERE Id = ?', $body['sql']);
        $this->assertEquals([3], $body['params']);
    }

    public function testDontParseParamSendsTheSqlUntouched(): void
    {
        $this->client->queueSuccess();
        $uri = self::URI . '?' . DbPdoDriver::DONT_PARSE_PARAM;
        $this->createExecutor($uri)->execute('SELECT * FROM Dogs WHERE Id = ?', [7]);

        $body = $this->client->getLastBody();

        $this->assertEquals('SELECT * FROM Dogs WHERE Id = ?', $body['sql']);
        $this->assertEquals([7], $body['params']);
    }

    public function testValuesAreNormalisedForJson(): void
    {
        $this->client->queueSuccess();
        $this->createExecutor()->execute(
            'INSERT INTO T (a, b, c, d, e) VALUES (:a, :b, :c, :d, :e)',
            [
                'a' => true,
                'b' => false,
                'c' => null,
                'd' => 3.75,
                'e' => new DateTime('2018-07-26 10:30:00'),
            ]
        );

        $this->assertEquals(
            [1, 0, null, 3.75, '2018-07-26 10:30:00'],
            $this->client->getLastBody()['params']
        );
    }

    public function testUnbindableValueIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot bind a value of type array');

        $this->createExecutor()->execute('SELECT * FROM Dogs WHERE Id = :id', ['id' => [1, 2]]);
    }

    // -------------------------------------------------------------------------- reading results

    public function testGetIteratorReturnsTheRows(): void
    {
        $this->client->queueSuccess([
            ['id' => 1, 'breed' => 'Mutt', 'name' => 'Spyke'],
            ['id' => 2, 'breed' => 'Brazilian Terrier', 'name' => 'Sandy'],
        ]);

        $iterator = $this->createExecutor()->getIterator('SELECT * FROM Dogs');

        $this->assertEquals(
            [
                ['id' => 1, 'breed' => 'Mutt', 'name' => 'Spyke'],
                ['id' => 2, 'breed' => 'Brazilian Terrier', 'name' => 'Sandy'],
            ],
            $iterator->toArray()
        );
        $this->assertFalse($iterator->isCursorOpen());
    }

    public function testGetScalarReturnsTheFirstColumn(): void
    {
        $this->client->queueSuccess([['total' => 3]]);

        $this->assertEquals(3, $this->createExecutor()->getScalar('SELECT count(*) as total FROM Dogs'));
    }

    public function testGetScalarWithoutRowsReturnsFalse(): void
    {
        $this->client->queueSuccess([]);

        $this->assertFalse($this->createExecutor()->getScalar('SELECT Id FROM Dogs WHERE Id = 1000'));
    }

    public function testGetIteratorHydratesAnEntityClass(): void
    {
        $this->client->queueSuccess([
            ['id' => 1, 'name' => 'Spyke', 'breed' => 'Mutt', 'weight' => 8.5],
            ['id' => 2, 'name' => 'Sandy', 'breed' => 'Brazilian Terrier', 'weight' => 3.8],
        ]);

        $sql = (new SqlStatement('SELECT * FROM Dogs'))->withEntityClass(Dogs::class);

        $names = [];
        foreach ($this->createExecutor()->getIterator($sql) as $row) {
            $entity = $row->entity();
            $this->assertInstanceOf(Dogs::class, $entity);
            $names[] = $entity->name;
        }

        $this->assertEquals(['Spyke', 'Sandy'], $names);
    }

    /**
     * The same expectations BasePdo uses for the PDO drivers: the buffer is measured while
     * iterating, and the cursor closes as soon as the underlying result set is drained.
     *
     * @return array<int, array{0: int, 1: array<int, int>, 2: array<int, bool>}>
     */
    public static function dataProviderPreFetch(): array
    {
        return [
            [0, [1, 1, 1], [true, true, true]],
            [1, [1, 1, 1], [true, true, true]],
            [2, [2, 2, 1], [true, true, false]],
            [3, [3, 2, 1], [true, false, false]],
            [50, [3, 2, 1], [false, false, false]],
        ];
    }

    /**
     * @param array<int, int> $expectedBuffer
     * @param array<int, bool> $expectedCursor
     */
    #[DataProvider('dataProviderPreFetch')]
    public function testPreFetchBuffersTheRequestedNumberOfRows(
        int $preFetch,
        array $expectedBuffer,
        array $expectedCursor
    ): void {
        $this->client->queueSuccess([['id' => 1], ['id' => 2], ['id' => 3]]);

        $iterator = $this->createExecutor()->getIterator('SELECT Id FROM Dogs', null, $preFetch);

        $i = 0;
        foreach ($iterator as $row) {
            $this->assertEquals(['id' => $i + 1], $row->toArray(), "Row $i");
            $this->assertEquals($expectedBuffer[$i], $iterator->getPreFetchBufferSize(), "Buffer $i");
            $this->assertEquals($expectedCursor[$i], $iterator->isCursorOpen(), "Cursor $i");
            $i++;
        }

        $this->assertEquals(3, $i);
        $this->assertFalse($iterator->isCursorOpen());
    }

    // ---------------------------------------------------------------------------- last insert id

    public function testExecuteAndGetIdUsesTheResponseMetadata(): void
    {
        // A single request must be enough: "select last_insert_rowid()" would run on another
        // connection and answer 0.
        $this->client->queueSuccess([], ['last_row_id' => 42, 'changes' => 1]);

        $id = $this->createExecutor()->executeAndGetId(
            'INSERT INTO Dogs (Name) VALUES (:name)',
            ['name' => 'Lola']
        );

        $this->assertEquals(42, $id);
        $this->assertEquals(1, $this->client->getRequestCount());
    }

    // ------------------------------------------------------------------- routing observability

    public function testResponseMetadataIsExposed(): void
    {
        $this->client->queueSuccess([['id' => 1]], [
            'duration' => 5,
            'rows_read' => 1,
            'served_by_primary' => false,
            'served_by_region' => 'WEUR',
            'served_by_colo' => 'LHR',
        ]);

        $driver = $this->createDriver();
        DatabaseExecutor::using($driver)->getIterator('SELECT Id FROM Dogs')->toArray();

        $this->assertFalse($driver->isServedByPrimary());
        $this->assertEquals('WEUR', $driver->getServedByRegion());
        $this->assertEquals('LHR', $driver->getServedByColo());
        $this->assertEquals(5, $driver->getLastMeta()['duration']);
        $this->assertEquals(1, $driver->getLastMeta()['rows_read']);
    }

    public function testRoutingMetadataIsNullWhenTheApiOmitsIt(): void
    {
        // A database without read replication does not report where the query was served.
        $this->client->queueRaw(200, (string)json_encode([
            'success' => true,
            'errors' => [],
            'result' => [['success' => true, 'results' => [], 'meta' => ['changes' => 0]]],
        ]));

        $driver = $this->createDriver();
        DatabaseExecutor::using($driver)->execute('DELETE FROM Dogs');

        $this->assertNull($driver->isServedByPrimary());
        $this->assertNull($driver->getServedByRegion());
        $this->assertNull($driver->getServedByColo());
    }

    public function testMetadataIsEmptyBeforeAnythingIsExecuted(): void
    {
        $driver = $this->createDriver();

        $this->assertEquals([], $driver->getLastMeta());
        $this->assertNull($driver->isServedByPrimary());
    }

    // --------------------------------------------------------------------------- error handling

    public function testApiErrorBecomesADatabaseException(): void
    {
        $this->client->queueError('no such table: Cats', 7500);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('[7500] no such table: Cats');

        $this->createExecutor()->execute('SELECT * FROM Cats');
    }

    public function testUnauthorizedBecomesADatabaseException(): void
    {
        $this->client->queueError('Invalid API Token', 10000, 401);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('[10000] Invalid API Token');

        $this->createExecutor()->execute('SELECT 1');
    }

    public function testNonJsonResponseBecomesADatabaseException(): void
    {
        $this->client->queueRaw(502, '<html>Bad Gateway</html>');

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Unexpected response from the Cloudflare D1 API (HTTP 502)');

        $this->createExecutor()->execute('SELECT 1');
    }

    public function testWrongStatementTypeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a D1Statement object');

        $this->createDriver()->executeCursor('not a statement');
    }

    // ----------------------------------------------------------------- connection and transactions

    public function testDisconnectMakesTheDriverUnusable(): void
    {
        $driver = $this->createDriver();
        $driver->disconnect();

        $this->expectException(DbDriverNotConnected::class);

        DatabaseExecutor::using($driver)->execute('SELECT 1');
    }

    public function testReconnect(): void
    {
        $driver = $this->createDriver();

        $this->assertFalse($driver->reconnect());
        $this->assertTrue($driver->reconnect(true));

        $driver->disconnect();
        $this->assertTrue($driver->reconnect());
    }

    public function testHardConnectionCheckProbesTheApi(): void
    {
        $this->client->queueSuccess([['1' => 1]]);
        $driver = $this->createDriver();

        $this->assertTrue($driver->isConnected());
        $this->assertEquals('SELECT 1', $this->client->getLastBody()['sql']);
    }

    public function testHardConnectionCheckFailsWhenTheApiRejects(): void
    {
        $this->client->queueError('Invalid API Token', 10000, 401);

        $this->assertFalse($this->createDriver()->isConnected());
    }

    public function testTransactionsAreNotAvailable(): void
    {
        $this->expectException(NotAvailableException::class);
        $this->expectExceptionMessage('does not support interactive transactions');

        $this->createDriver()->beginTransaction();
    }

    public function testDefaultHttpClientIsResolvedWhenNoneIsInjected(): void
    {
        // No setHttpClient() here: the driver must fall back to the byjg/webrequest client.
        $driver = new DbD1Driver(new Uri(self::URI));

        $this->assertInstanceOf(HttpClient::class, $driver->getDbConnection());
    }

    public function testGetDbConnectionIsNullWhenDisconnected(): void
    {
        $driver = $this->createDriver();
        $driver->disconnect();

        $this->assertNull($driver->getDbConnection());
    }

    public function testMultiRowsetIsNotSupported(): void
    {
        $driver = $this->createDriver();

        $this->assertFalse($driver->isSupportMultiRowset());

        $this->expectException(NotAvailableException::class);
        $driver->setSupportMultiRowset(true);
    }
}
