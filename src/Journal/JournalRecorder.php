<?php

namespace ByJG\AnyDataset\Db\Journal;

use ByJG\AnyDataset\Db\DatabaseEvent;
use ByJG\AnyDataset\Db\DatabaseEventTypeEnum;
use ByJG\AnyDataset\Db\Exception\JournalException;
use ByJG\AnyDataset\Db\Interfaces\DatabaseEventObserverInterface;
use Throwable;

/**
 * Observer that records all INSERT, UPDATE and DELETE statements executed
 * through a DatabaseExecutor, capturing the row values before and after
 * each operation.
 *
 * Attach it to an executor with `$executor->addObserver($recorder)`.
 * It can watch ALL tables (default) or a specific set of tables.
 *
 * The journal can later be replayed in reverse by the JournalRestorer
 * to return the database to the state before the recording started
 * (e.g. PHPUnit setUp/tearDown).
 *
 * Limitations: only single-table DML statements are recognized
 * (INSERT INTO ... VALUES, UPDATE ... SET ... WHERE, DELETE FROM ... WHERE).
 * Multi-statement SQL, INSERT...SELECT and multi-table UPDATEs are ignored.
 */
class JournalRecorder implements DatabaseEventObserverInterface
{
    /** @var JournalEntry[] */
    protected array $entries = [];

    /** @var string[]|null Lowercased table names to watch; null = all tables */
    protected ?array $tables = null;

    /** @var array<string, string> Map of lowercased table name => primary key column */
    protected array $primaryKeys = [];

    protected string $defaultPrimaryKey = 'id';

    protected bool $strict = false;

    /**
     * Stack of pending operations captured on BEFORE_EXECUTE and
     * consumed on AFTER_EXECUTE.
     *
     * @var array<int, array{dml: ParsedDml, oldRows: array<int, array<string, mixed>>}|null>
     */
    protected array $pending = [];

    /**
     * @param string[]|null $tables Tables to watch; null watches all tables
     */
    public function __construct(?array $tables = null)
    {
        $this->tables = is_null($tables) ? null : array_map('strtolower', $tables);
    }

    /**
     * Create a recorder watching all tables.
     */
    public static function forAllTables(): static
    {
        return new static();
    }

    /**
     * Create a recorder watching only the given tables.
     */
    public static function forTables(string ...$tables): static
    {
        return new static($tables);
    }

    /**
     * Define the primary key column of a table (default is "id").
     * The primary key is used to capture the rows after an UPDATE and
     * to delete inserted rows on restore.
     */
    public function withPrimaryKey(string $table, string $column): static
    {
        $this->primaryKeys[strtolower($table)] = $column;
        return $this;
    }

    /**
     * Change the primary key column assumed for tables without
     * an explicit definition (default is "id").
     */
    public function withDefaultPrimaryKey(string $column): static
    {
        $this->defaultPrimaryKey = $column;
        return $this;
    }

    /**
     * Enable strict mode: instead of silently skipping, the recorder throws
     * a JournalException when it cannot guarantee the operation can be restored:
     *
     * - a write statement (INSERT/UPDATE/DELETE/REPLACE/MERGE/TRUNCATE) cannot be
     *   parsed (thrown BEFORE the statement executes, so the database is not modified);
     * - the rows affected by an UPDATE/DELETE cannot be captured (also thrown
     *   before execution);
     * - the values of an INSERT cannot be determined (thrown after execution).
     *
     * Non-write statements (SELECT, DDL such as CREATE/DROP/ALTER) are never affected.
     *
     * Recommended when the journal is used to restore state between tests, where
     * a silently incomplete journal would leak state to the next test.
     */
    public function strict(bool $strict = true): static
    {
        $this->strict = $strict;
        return $this;
    }

    public function isStrict(): bool
    {
        return $this->strict;
    }

    /**
     * @return JournalEntry[]
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function clear(): void
    {
        $this->entries = [];
        $this->pending = [];
    }

    public function primaryKeyFor(string $table): string
    {
        return $this->primaryKeys[strtolower($table)] ?? $this->defaultPrimaryKey;
    }

    #[\Override]
    public function subscribedEvents(): array
    {
        return [DatabaseEventTypeEnum::BEFORE_EXECUTE, DatabaseEventTypeEnum::AFTER_EXECUTE];
    }

    #[\Override]
    public function handleEvent(DatabaseEvent $event): void
    {
        if ($event->getType() === DatabaseEventTypeEnum::BEFORE_EXECUTE) {
            $this->beforeExecute($event);
        } elseif ($event->getType() === DatabaseEventTypeEnum::AFTER_EXECUTE) {
            $this->afterExecute($event);
        }
    }

    protected function isWatched(string $table): bool
    {
        return is_null($this->tables) || in_array(strtolower($table), $this->tables, true);
    }

    protected function beforeExecute(DatabaseEvent $event): void
    {
        $sql = $event->getStatement()->getSql();
        $dml = DmlParser::parse($sql);

        if (is_null($dml)) {
            // In strict mode a write statement that cannot be journaled is rejected
            // BEFORE it executes. The target table cannot be determined, so this
            // applies even when watching only specific tables.
            if ($this->strict && $this->looksLikeWrite($sql)) {
                throw new JournalException(
                    "Journal strict mode: cannot journal statement (unsupported DML shape). " .
                    "The statement was NOT executed. SQL: $sql"
                );
            }
            $this->pending[] = null;
            return;
        }

        if (!$this->isWatched($dml->getTable())) {
            $this->pending[] = null;
            return;
        }

        $oldRows = [];
        if ($dml->getOperation() !== JournalOperationEnum::INSERT) {
            try {
                $oldRows = $this->fetchRows(
                    $event,
                    $dml->getTable(),
                    $dml->getWhere(),
                    $event->getStatement()->getParams()
                );
            } catch (Throwable $ex) {
                if ($this->strict) {
                    throw new JournalException(
                        "Journal strict mode: could not capture the rows affected by the statement, " .
                        "so it would not be restorable. The statement was NOT executed. " .
                        "SQL: $sql. Reason: " . $ex->getMessage(),
                        0,
                        $ex
                    );
                }
                // Never let journaling break the main statement.
                $event->getExecutor()->getDriver()->log(
                    "Journal: could not capture old rows for '{$dml->getTable()}': " . $ex->getMessage()
                );
                $this->pending[] = null;
                return;
            }
        }

        $this->pending[] = ['dml' => $dml, 'oldRows' => $oldRows];
    }

    /**
     * Whether the statement looks like a data modification statement.
     */
    protected function looksLikeWrite(string $sql): bool
    {
        return preg_match('~^\s*(insert|update|delete|replace|merge|truncate)\b~i', $sql) === 1;
    }

    protected function afterExecute(DatabaseEvent $event): void
    {
        $pending = array_pop($this->pending);
        if (is_null($pending)) {
            return;
        }

        $dml = $pending['dml'];
        $oldRows = $pending['oldRows'];
        $statement = $event->getStatement();

        try {
            $newRows = match ($dml->getOperation()) {
                JournalOperationEnum::INSERT => $this->captureInsertedRows($event, $dml),
                JournalOperationEnum::UPDATE => $this->captureUpdatedRows($event, $dml, $oldRows),
                JournalOperationEnum::DELETE => [],
            };
        } catch (Throwable $ex) {
            $event->getExecutor()->getDriver()->log(
                "Journal: could not capture new rows for '{$dml->getTable()}': " . $ex->getMessage()
            );
            $newRows = [];
        }

        // Without the new values an INSERT cannot be reverted. An UPDATE/DELETE is
        // still restorable from the old rows, so only the INSERT is fatal in strict mode.
        if ($this->strict && $dml->getOperation() === JournalOperationEnum::INSERT && empty($newRows)) {
            throw new JournalException(
                "Journal strict mode: the inserted values could not be captured, so the INSERT " .
                "cannot be reverted. NOTE: the statement WAS executed. SQL: {$statement->getSql()}"
            );
        }

        $this->entries[] = new JournalEntry(
            $dml->getOperation(),
            $dml->getTable(),
            $oldRows,
            $newRows,
            $statement->getSql(),
            $statement->getParams() ?? []
        );
    }

    /**
     * Capture the row just inserted, preferring a SELECT by the generated id.
     * Falls back to the values parsed from the INSERT statement.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function captureInsertedRows(DatabaseEvent $event, ParsedDml $dml): array
    {
        $executor = $event->getExecutor();
        $resolvedValues = $dml->resolveInsertValues($event->getStatement()->getParams());

        try {
            $primaryKey = $this->primaryKeyFor($dml->getTable());
            $lastId = $executor->getScalar($executor->getHelper()->getSqlLastInsertId());

            if (!empty($lastId)) {
                $rows = $this->fetchRows(
                    $event,
                    $dml->getTable(),
                    "$primaryKey = :journal_pk_value",
                    ['journal_pk_value' => $lastId]
                );

                // The last-insert-id can be stale when the table has no auto increment
                // column. Only trust the fetched row if it matches the statement values.
                if (count($rows) === 1 && $this->rowMatches($rows[0], $resolvedValues)) {
                    return $rows;
                }
            }
        } catch (Throwable $ex) {
            // e.g. PostgreSQL lastval() throws when no sequence was used in the session
            $executor->getDriver()->log(
                "Journal: could not fetch inserted row for '{$dml->getTable()}': " . $ex->getMessage()
            );
        }

        return empty($resolvedValues) ? [] : [$resolvedValues];
    }

    /**
     * Capture the rows after an UPDATE, selecting them by the primary key
     * of the rows captured before the operation. When the primary key is not
     * available, re-run the original WHERE clause as a best effort.
     *
     * @param array<int, array<string, mixed>> $oldRows
     * @return array<int, array<string, mixed>>
     */
    protected function captureUpdatedRows(DatabaseEvent $event, ParsedDml $dml, array $oldRows): array
    {
        if (empty($oldRows)) {
            return [];
        }

        $primaryKey = $this->primaryKeyFor($dml->getTable());
        $pkColumn = $this->findKey($oldRows[0], $primaryKey);

        if (is_null($pkColumn)) {
            // Fallback: re-run the original WHERE clause. Note this can miss rows
            // if the UPDATE changed a column referenced in the WHERE clause.
            return $this->fetchRows(
                $event,
                $dml->getTable(),
                $dml->getWhere(),
                $event->getStatement()->getParams()
            );
        }

        $newRows = [];
        foreach ($oldRows as $oldRow) {
            $rows = $this->fetchRows(
                $event,
                $dml->getTable(),
                "$pkColumn = :journal_pk_value",
                ['journal_pk_value' => $oldRow[$pkColumn]]
            );
            foreach ($rows as $row) {
                $newRows[] = $row;
            }
        }

        return $newRows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchRows(DatabaseEvent $event, string $table, ?string $where, ?array $params): array
    {
        $sql = "SELECT * FROM $table" . (empty($where) ? "" : " WHERE $where");
        $iterator = $event->getExecutor()->getIterator($sql, $params ?? []);

        $rows = [];
        foreach ($iterator as $row) {
            $rows[] = $row->toArray();
        }
        return $rows;
    }

    /**
     * Find a key in the row matching the given name (case-insensitive).
     */
    protected function findKey(array $row, string $name): ?string
    {
        foreach (array_keys($row) as $key) {
            if (strcasecmp((string)$key, $name) === 0) {
                return (string)$key;
            }
        }
        return null;
    }

    /**
     * Loosely compare a fetched row against the values resolved from
     * the INSERT statement.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $values
     */
    protected function rowMatches(array $row, array $values): bool
    {
        foreach ($values as $column => $value) {
            $key = $this->findKey($row, $column);
            if (is_null($key)) {
                continue;
            }
            if (is_null($value) || is_null($row[$key])) {
                if (!is_null($value) || !is_null($row[$key])) {
                    return false;
                }
                continue;
            }
            if (is_numeric($row[$key]) && is_numeric($value)) {
                if ((float)$row[$key] !== (float)$value) {
                    return false;
                }
                continue;
            }
            if ((string)$row[$key] !== (string)$value) {
                return false;
            }
        }
        return true;
    }
}