<?php

namespace ByJG\AnyDataset\Db\Journal;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Exception\JournalRestoreException;
use ByJG\AnyDataset\Db\Interfaces\DbDriverInterface;

/**
 * Replays a JournalRecorder in reverse order, returning the database to the
 * state it had before the recording started:
 *
 * - recorded INSERTs are deleted;
 * - recorded UPDATEs have their old values written back;
 * - recorded DELETEs are re-inserted.
 *
 * Typical usage in a functional test:
 *
 * <code>
 * // setUp
 * $recorder = JournalRecorder::forAllTables();
 * $executor->addObserver($recorder);
 *
 * // tearDown
 * $executor->removeObserver($recorder);
 * (new JournalRestorer())->restore($executor->getDriver(), $recorder);
 * </code>
 */
class JournalRestorer
{
    /**
     * Restore the database state by replaying the journal in reverse.
     *
     * The restore statements are executed through a dedicated executor
     * (without observers), so they are not recorded again.
     *
     * @param DbDriverInterface $driver
     * @param JournalRecorder $recorder
     * @param bool $clear Clear the recorder after a successful restore (default true)
     * @return void
     */
    public function restore(DbDriverInterface $driver, JournalRecorder $recorder, bool $clear = true): void
    {
        $executor = DatabaseExecutor::using($driver);

        foreach (array_reverse($recorder->getEntries()) as $entry) {
            match ($entry->getOperation()) {
                JournalOperationEnum::INSERT => $this->revertInsert($executor, $recorder, $entry),
                JournalOperationEnum::UPDATE => $this->revertUpdate($executor, $recorder, $entry),
                JournalOperationEnum::DELETE => $this->revertDelete($executor, $entry),
            };
        }

        if ($clear) {
            $recorder->clear();
        }
    }

    protected function revertInsert(DatabaseExecutor $executor, JournalRecorder $recorder, JournalEntry $entry): void
    {
        if (empty($entry->getNewRows())) {
            throw new JournalRestoreException(
                "Cannot revert INSERT on '{$entry->getTable()}': no new values were captured. SQL: {$entry->getSql()}"
            );
        }

        $primaryKey = $recorder->primaryKeyFor($entry->getTable());

        foreach ($entry->getNewRows() as $row) {
            $pkColumn = $this->findKey($row, $primaryKey);
            if (!is_null($pkColumn) && !is_null($row[$pkColumn])) {
                $executor->execute(
                    "DELETE FROM {$entry->getTable()} WHERE $pkColumn = :journal_pk_value",
                    ['journal_pk_value' => $row[$pkColumn]]
                );
                continue;
            }

            // No primary key available: delete by matching all captured values
            [$where, $params] = $this->buildMatchClause($row);
            $executor->execute("DELETE FROM {$entry->getTable()} WHERE $where", $params);
        }
    }

    protected function revertUpdate(DatabaseExecutor $executor, JournalRecorder $recorder, JournalEntry $entry): void
    {
        $primaryKey = $recorder->primaryKeyFor($entry->getTable());

        foreach ($entry->getOldRows() as $row) {
            $pkColumn = $this->findKey($row, $primaryKey);
            if (is_null($pkColumn)) {
                throw new JournalRestoreException(
                    "Cannot revert UPDATE on '{$entry->getTable()}': primary key column '$primaryKey' " .
                    "was not found in the captured rows. Configure it with JournalRecorder::withPrimaryKey()."
                );
            }

            $set = [];
            $params = ['journal_pk_value' => $row[$pkColumn]];
            foreach ($row as $column => $value) {
                if ($column === $pkColumn) {
                    continue;
                }
                $paramName = 'journal_set_' . count($params);
                $set[] = "$column = :$paramName";
                $params[$paramName] = $value;
            }

            if (empty($set)) {
                continue;
            }

            $executor->execute(
                "UPDATE {$entry->getTable()} SET " . implode(', ', $set) .
                " WHERE $pkColumn = :journal_pk_value",
                $params
            );
        }
    }

    protected function revertDelete(DatabaseExecutor $executor, JournalEntry $entry): void
    {
        foreach ($entry->getOldRows() as $row) {
            $columns = [];
            $placeholders = [];
            $params = [];
            foreach ($row as $column => $value) {
                $paramName = 'journal_val_' . count($params);
                $columns[] = $column;
                $placeholders[] = ":$paramName";
                $params[$paramName] = $value;
            }

            $executor->execute(
                "INSERT INTO {$entry->getTable()} (" . implode(', ', $columns) . ") " .
                "VALUES (" . implode(', ', $placeholders) . ")",
                $params
            );
        }
    }

    /**
     * Build a WHERE clause matching all the row values (NULL-safe).
     *
     * @param array<string, mixed> $row
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function buildMatchClause(array $row): array
    {
        $conditions = [];
        $params = [];
        foreach ($row as $column => $value) {
            if (is_null($value)) {
                $conditions[] = "$column IS NULL";
                continue;
            }
            $paramName = 'journal_match_' . count($params);
            $conditions[] = "$column = :$paramName";
            $params[$paramName] = $value;
        }

        return [implode(' AND ', $conditions), $params];
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
}