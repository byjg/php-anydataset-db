<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\Interfaces\DbDriverInterface;
use ByJG\AnyDataset\Db\Interfaces\SqlDialectInterface;
use ByJG\AnyDataset\Db\SqlDialect\D1Dialect;
use ByJG\AnyDataset\Db\Traits\DbCacheTrait;
use ByJG\AnyDataset\Db\Traits\TransactionTrait;
use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;
use ByJG\Util\Uri;
use DateTimeInterface;
use InvalidArgumentException;
use Override;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stringable;

/**
 * Driver for Cloudflare D1, the SQLite compatible database served over the Cloudflare REST API.
 *
 * D1 has no wire protocol and therefore no PDO driver: every statement is a POST to
 * "/accounts/{account_id}/d1/database/{database_id}/query". This driver implements the
 * DbDriverInterface on top of any PSR-18 HTTP client, following the same structure used by the
 * other native (non PDO) driver, DbOci8Driver.
 *
 * Connection URI:
 *
 *     d1://{account_id}:{api_token}@api.cloudflare.com/{database_id}
 *
 * Optional query parameters:
 *   - apischeme: "https" (default) or "http", useful to point the driver at a local mock
 *   - basepath:  the API base path, "/client/v4" by default
 *
 * Note on read replication: the Sessions API, which keeps reads consistent with your own writes by
 * threading a bookmark through the queries, is only available through the D1 Workers binding and
 * not through the REST API. The "/query" endpoint has no bookmark field, so this driver cannot ask
 * for sequential consistency; isServedByPrimary() and getServedByRegion() expose what D1 reports
 * about the routing. See docs/cloudflare-d1.md.
 *
 * @see https://developers.cloudflare.com/api/resources/d1/subresources/database/methods/query/
 */
class DbD1Driver implements DbDriverInterface
{
    use DbCacheTrait;
    use TransactionTrait;

    public const string DEFAULT_HOST = 'api.cloudflare.com';
    public const string DEFAULT_BASE_PATH = '/client/v4';

    /**
     * Matches a single quoted SQL literal (which must be left untouched) or a named ":parameter".
     */
    private const string PARAM_PATTERN = <<<'REGEX'
        ~'(?:\\.|''|[^'\\])*'|(?<!:):(?<param>[_\w\d]+)\b~
        REGEX;

    protected Uri $connectionUri;

    protected LoggerInterface $logger;

    protected ?SqlDialectInterface $sqlDialect = null;

    protected bool $connected = false;

    protected string $accountId;

    protected string $databaseId;

    protected string $apiToken;

    protected string $endpoint;

    protected ?ClientInterface $httpClient = null;

    protected ?RequestFactoryInterface $requestFactory = null;

    protected ?StreamFactoryInterface $streamFactory = null;

    protected ?D1Statement $lastStatement = null;

    /**
     * @throws InvalidArgumentException When the URI does not carry the account, token and database
     */
    public function __construct(Uri $connectionUri)
    {
        $this->logger = new NullLogger();
        $this->connectionUri = $connectionUri;

        $this->accountId = (string)$connectionUri->getUsername();
        $this->apiToken = (string)$connectionUri->getPassword();
        $this->databaseId = trim($connectionUri->getPath(), '/');

        if (empty($this->accountId) || empty($this->apiToken) || empty($this->databaseId)) {
            throw new InvalidArgumentException(
                'The D1 connection URI must be in the form '
                . 'd1://{account_id}:{api_token}@api.cloudflare.com/{database_id}'
            );
        }

        $this->endpoint = $this->buildEndpoint($connectionUri);

        // There is no connection to establish: the API is stateless and every statement is a
        // self-contained HTTP request. Credentials are only checked when a request is actually made.
        $this->connected = true;
    }

    #[Override]
    public static function schema(): array
    {
        return ['d1'];
    }

    #[Override]
    public function getSqlDialectClass(): string
    {
        return D1Dialect::class;
    }

    private function buildEndpoint(Uri $uri): string
    {
        $host = $uri->getHost() ?: self::DEFAULT_HOST;
        $port = $uri->getPort();
        $scheme = $uri->getQueryPart('apischeme') ?? 'https';
        $basePath = rtrim($uri->getQueryPart('basepath') ?? self::DEFAULT_BASE_PATH, '/');

        return sprintf(
            '%s://%s%s%s/accounts/%s/d1/database/%s/query',
            $scheme,
            $host,
            empty($port) ? '' : ':' . $port,
            $basePath,
            rawurlencode($this->accountId),
            rawurlencode($this->databaseId)
        );
    }

    /**
     * Replace the PSR-18 client (and optionally the PSR-17 factories) used to reach the D1 API.
     */
    public function setHttpClient(
        ClientInterface $httpClient,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ): static {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory ?? $this->requestFactory;
        $this->streamFactory = $streamFactory ?? $this->streamFactory;

        return $this;
    }

    /**
     * @throws NotAvailableException When no PSR-18 client is available
     */
    protected function getHttpClient(): ClientInterface
    {
        if (is_null($this->httpClient)) {
            if (!class_exists('\ByJG\WebRequest\HttpClient')) {
                throw new NotAvailableException(
                    'No PSR-18 HTTP client available for the D1 driver. '
                    . 'Run "composer require byjg/webrequest" or inject one with setHttpClient().'
                );
            }
            /** @var ClientInterface $client */
            $client = new \ByJG\WebRequest\HttpClient();
            $this->httpClient = $client;
        }

        return $this->httpClient;
    }

    /**
     * @throws NotAvailableException When no PSR-17 request factory is available
     */
    protected function getRequestFactory(): RequestFactoryInterface
    {
        if (is_null($this->requestFactory)) {
            if (!class_exists('\ByJG\WebRequest\Factory\RequestFactory')) {
                throw new NotAvailableException(
                    'No PSR-17 request factory available for the D1 driver. '
                    . 'Run "composer require byjg/webrequest" or inject one with setHttpClient().'
                );
            }
            /** @var RequestFactoryInterface $factory */
            $factory = new \ByJG\WebRequest\Factory\RequestFactory();
            $this->requestFactory = $factory;
        }

        return $this->requestFactory;
    }

    /**
     * @throws NotAvailableException When no PSR-17 stream factory is available
     */
    protected function getStreamFactory(): StreamFactoryInterface
    {
        if (is_null($this->streamFactory)) {
            if (!class_exists('\ByJG\WebRequest\Factory\StreamFactory')) {
                throw new NotAvailableException(
                    'No PSR-17 stream factory available for the D1 driver. '
                    . 'Run "composer require byjg/webrequest" or inject one with setHttpClient().'
                );
            }
            /** @var StreamFactoryInterface $factory */
            $factory = new \ByJG\WebRequest\Factory\StreamFactory();
            $this->streamFactory = $factory;
        }

        return $this->streamFactory;
    }

    /**
     * Convert the named parameters used across the library into the positional "?" placeholders
     * expected by the D1 API.
     *
     * Placeholders inside single quoted literals are preserved, a placeholder without a matching
     * parameter becomes the literal "null" (the same behaviour as ParameterBinder), and a parameter
     * used more than once binds its value once per occurrence.
     *
     * @param array<string, mixed>|null $params
     * @return array{0: string, 1: array<int, mixed>} The rewritten SQL and its positional parameters
     */
    protected function bindPositional(string $sql, ?array $params = null): array
    {
        $params = $params ?? [];
        $bound = [];

        $parsed = preg_replace_callback(
            self::PARAM_PATTERN,
            function (array $match) use (&$bound, $params): string {
                // The alternation matched a quoted literal, not a placeholder.
                if (!isset($match['param']) || $match['param'] === '') {
                    return $match[0];
                }

                $name = $match['param'];
                if (!array_key_exists($name, $params)) {
                    return 'null';
                }

                $bound[] = $this->normalizeValue($params[$name]);

                return '?';
            },
            $sql
        );

        return [$parsed ?? $sql, $bound];
    }

    /**
     * D1 accepts JSON scalars as bound parameters. Convert the PHP values the library commonly
     * carries into something the API understands, and refuse anything that cannot be represented.
     */
    protected function normalizeValue(mixed $value): string|int|float|null
    {
        return match (true) {
            is_null($value), is_int($value), is_float($value), is_string($value) => $value,
            is_bool($value) => $value ? 1 : 0,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof Stringable => (string)$value,
            default => throw new InvalidArgumentException(
                'Cannot bind a value of type ' . get_debug_type($value) . ' to a Cloudflare D1 statement'
            ),
        };
    }

    /**
     * @throws DbDriverNotConnected
     */
    #[Override]
    public function prepareStatement(string $sql, ?array $params = null, ?array &$cacheInfo = []): D1Statement
    {
        $this->isConnected(true, true);

        if ($this->connectionUri->hasQueryKey(DbPdoDriver::DONT_PARSE_PARAM)) {
            $statement = new D1Statement($sql, array_values($params ?? []));
        } else {
            [$parsedSql, $positionalParams] = $this->bindPositional($sql, $params);
            $statement = new D1Statement($parsedSql, $positionalParams);
        }

        $this->logger->debug(
            "SQL: {$statement->getSql()}\nParams: " . (json_encode($statement->getParams()) ?: '[]')
        );

        return $statement;
    }

    /**
     * Sends the statement to the D1 API and stores the returned rows and metadata on it.
     *
     * @throws DatabaseException|DbDriverNotConnected|NotAvailableException
     */
    #[Override]
    public function executeCursor(mixed $statement): void
    {
        if (!($statement instanceof D1Statement)) {
            throw new InvalidArgumentException('The statement parameter must be a D1Statement object');
        }

        $this->isConnected(true, true);

        [$rows, $meta] = $this->postQuery($statement);
        $statement->setResult($rows, $meta);
        $this->lastStatement = $statement;
    }

    /**
     * Performs the actual HTTP round trip and unwraps the D1 response envelope.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>}
     * @throws DatabaseException|DbDriverNotConnected|NotAvailableException
     */
    protected function postQuery(D1Statement $statement): array
    {
        $payload = json_encode($statement->toRequestBody());
        if ($payload === false) {
            throw new DatabaseException('Unable to encode the D1 request body: ' . json_last_error_msg());
        }

        $request = $this->getRequestFactory()
            ->createRequest('POST', $this->endpoint)
            ->withHeader('Authorization', 'Bearer ' . $this->apiToken)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->getStreamFactory()->createStream($payload));

        try {
            $response = $this->getHttpClient()->sendRequest($request);
        } catch (ClientExceptionInterface $ex) {
            throw new DbDriverNotConnected('Unable to reach the Cloudflare D1 API: ' . $ex->getMessage());
        }

        $body = (string)$response->getBody();
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new DatabaseException(
                'Unexpected response from the Cloudflare D1 API (HTTP ' . $response->getStatusCode() . '): ' . $body
            );
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300 || ($decoded['success'] ?? false) !== true) {
            throw new DatabaseException($this->formatErrors($decoded, $statusCode));
        }

        $result = $decoded['result'][0] ?? null;
        if (!is_array($result)) {
            throw new DatabaseException('The Cloudflare D1 API returned no result for the statement');
        }

        if (($result['success'] ?? true) !== true) {
            throw new DatabaseException($this->formatErrors($result, $statusCode));
        }

        $rows = $result['results'] ?? [];
        $meta = $result['meta'] ?? [];

        return [
            is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [],
            is_array($meta) ? $meta : [],
        ];
    }

    /**
     * Turns the "errors" array of a D1 envelope into a single readable message.
     *
     * @param array<string, mixed> $envelope
     */
    protected function formatErrors(array $envelope, int $statusCode): string
    {
        $errors = $envelope['errors'] ?? [];
        $messages = [];

        if (is_array($errors)) {
            foreach ($errors as $error) {
                if (!is_array($error)) {
                    continue;
                }
                $code = $error['code'] ?? null;
                $message = (string)($error['message'] ?? 'Unknown error');
                $messages[] = is_null($code) ? $message : "[$code] $message";
            }
        }

        if (empty($messages)) {
            $messages[] = 'The Cloudflare D1 API returned HTTP ' . $statusCode . ' without an error description';
        }

        return implode('; ', $messages);
    }

    /**
     * D1 answers one result object per request, so there is no additional rowset to consume.
     */
    #[Override]
    public function processMultiRowset(mixed $statement): void
    {
        // Intentionally empty: the driver does not support multiple rowsets.
    }

    #[Override]
    public function getDriverIterator(
        mixed $statement,
        int $preFetch = 0,
        ?string $entityClass = null,
        ?PropertyHandlerInterface $entityTransformer = null
    ): GenericDbIterator|GenericIterator {
        if (!($statement instanceof D1Statement)) {
            throw new InvalidArgumentException('The argument needs to be a D1Statement object');
        }

        return new D1Iterator($statement, $preFetch, $entityClass, $entityTransformer);
    }

    /**
     * The rowid generated by the last executed statement, as reported by D1 in "meta.last_row_id".
     *
     * D1 is stateless, so "SELECT last_insert_rowid()" would run on a different connection and
     * return 0. The response metadata is the only reliable source. Used by D1Dialect.
     */
    public function getLastRowId(): ?int
    {
        return $this->lastStatement?->getLastRowId();
    }

    /**
     * The raw "meta" object D1 returned for the last executed statement.
     *
     * Besides the last inserted id it carries timing and routing information: "duration",
     * "rows_read", "rows_written", "size_after", "served_by_primary", "served_by_region", ...
     *
     * @return array<string, mixed> Empty when nothing has been executed yet
     */
    public function getLastMeta(): array
    {
        return $this->lastStatement?->getMeta() ?? [];
    }

    /**
     * Whether the last statement was served by the primary database rather than by a read replica.
     *
     * Only meaningful when read replication is enabled on the database. Because the Sessions API is
     * not available over the REST API, this driver cannot pin a query to a bookmark; this accessor
     * is for observability. Returns null when D1 did not report it.
     *
     * @see https://developers.cloudflare.com/d1/best-practices/read-replication/
     */
    public function isServedByPrimary(): ?bool
    {
        return $this->lastStatement?->isServedByPrimary();
    }

    /**
     * The region that served the last statement, e.g. "WNAM", "ENAM", "WEUR", "EEUR", "APAC", "OC".
     */
    public function getServedByRegion(): ?string
    {
        return $this->lastStatement?->getServedByRegion();
    }

    /**
     * The Cloudflare colo (airport code) that served the last statement, e.g. "LHR".
     */
    public function getServedByColo(): ?string
    {
        return $this->lastStatement?->getServedByColo();
    }

    #[Override]
    public function getDbConnection(): mixed
    {
        return $this->connected ? $this->getHttpClient() : null;
    }

    #[Override]
    public function getSqlDialect(): SqlDialectInterface
    {
        if (empty($this->sqlDialect)) {
            $dialectClass = $this->getSqlDialectClass();
            $this->sqlDialect = new $dialectClass();
        }

        return $this->sqlDialect;
    }

    #[Override]
    public function getUri(): Uri
    {
        return $this->connectionUri;
    }

    #[Override]
    public function isSupportMultiRowset(): bool
    {
        return false;
    }

    #[Override]
    public function setSupportMultiRowset(bool $multipleRowSet): void
    {
        if ($multipleRowSet) {
            throw new NotAvailableException('Cloudflare D1 does not support multiple rowsets');
        }
    }

    /**
     * @throws DbDriverNotConnected
     */
    #[Override]
    public function isConnected(bool $softCheck = false, bool $throwError = false): bool
    {
        if (!$this->connected) {
            if ($throwError) {
                throw new DbDriverNotConnected('DbDriver not connected');
            }
            return false;
        }

        if ($softCheck) {
            return true;
        }

        try {
            $this->postQuery(new D1Statement('SELECT 1'));
        } catch (DatabaseException | DbDriverNotConnected | NotAvailableException) {
            if ($throwError) {
                throw new DbDriverNotConnected('DbDriver not connected');
            }
            return false;
        }

        return true;
    }

    /**
     * There is no persistent connection to re-establish; this only clears the disconnected flag.
     */
    #[Override]
    public function reconnect(bool $force = false): bool
    {
        if ($this->isConnected(true) && !$force) {
            return false;
        }

        $this->connected = true;

        return true;
    }

    #[Override]
    public function disconnect(): void
    {
        $this->connected = false;
        $this->lastStatement = null;
    }

    #[Override]
    public function enableLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    #[Override]
    public function log(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * Cloudflare D1 has no interactive transactions: the REST API rejects BEGIN/COMMIT with
     * "cannot start a transaction within a transaction", because every request is already wrapped
     * in its own implicit transaction.
     *
     * @throws NotAvailableException
     */
    protected function transactionHandler(TransactionStageEnum $action, string $isoLevelCommand = ""): void
    {
        throw new NotAvailableException(
            'Cloudflare D1 does not support interactive transactions. '
            . 'Each statement is executed and committed atomically on its own.'
        );
    }
}
