<?php

namespace ByJG\AnyDataset\Db;

/**
 * Represents a statement to be executed against the Cloudflare D1 HTTP API.
 *
 * This is the D1 counterpart of a PDOStatement (or an OCI8 statement resource): it is created by
 * DbD1Driver::prepareStatement() holding the SQL and its positional parameters, and it is filled
 * with the returned rows and metadata by DbD1Driver::executeCursor().
 */
class D1Statement
{
    /**
     * @var array<int, mixed> Positional parameters, in the order the placeholders appear in the SQL
     */
    private array $params;

    /**
     * @var array<int, array<string, mixed>>|null The returned rows, or null while not executed
     */
    private ?array $rows = null;

    /**
     * @var array<string, mixed> The "meta" object returned by D1 (changes, last_row_id, duration, ...)
     */
    private array $meta = [];

    /**
     * @param string $sql The SQL with positional "?" placeholders
     * @param array<int, mixed> $params
     */
    public function __construct(private readonly string $sql, array $params = [])
    {
        $this->params = array_values($params);
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<int, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * The request body expected by the D1 "/query" endpoint.
     *
     * @return array<string, mixed>
     */
    public function toRequestBody(): array
    {
        return [
            'sql' => $this->sql,
            'params' => $this->params,
        ];
    }

    public function isExecuted(): bool
    {
        return !is_null($this->rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $meta
     */
    public function setResult(array $rows, array $meta): void
    {
        $this->rows = array_values($rows);
        $this->meta = $meta;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        return $this->rows ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * The rowid of the last inserted row, as reported by D1 in the response metadata.
     */
    public function getLastRowId(): ?int
    {
        $lastRowId = $this->meta['last_row_id'] ?? null;

        return is_numeric($lastRowId) ? (int)$lastRowId : null;
    }

    /**
     * The number of rows changed by the statement, as reported by D1.
     */
    public function getChanges(): int
    {
        $changes = $this->meta['changes'] ?? 0;

        return is_numeric($changes) ? (int)$changes : 0;
    }

    /**
     * Whether D1 served the statement from the primary database rather than from a read replica.
     *
     * Returns null when the API did not report it. See the "Read replication" section of
     * docs/cloudflare-d1.md.
     */
    public function isServedByPrimary(): ?bool
    {
        $servedByPrimary = $this->meta['served_by_primary'] ?? null;

        return is_null($servedByPrimary) ? null : (bool)$servedByPrimary;
    }

    /**
     * The region that served the statement, e.g. "WNAM", "ENAM", "WEUR", "EEUR", "APAC" or "OC".
     */
    public function getServedByRegion(): ?string
    {
        $region = $this->meta['served_by_region'] ?? null;

        return is_scalar($region) ? (string)$region : null;
    }

    /**
     * The Cloudflare colo (airport code) that served the statement, e.g. "LHR".
     */
    public function getServedByColo(): ?string
    {
        $colo = $this->meta['served_by_colo'] ?? null;

        return is_scalar($colo) ? (string)$colo : null;
    }
}
