<?php

namespace ByJG\AnyDataset\Db\Journal;

/**
 * Result of parsing a DML (INSERT/UPDATE/DELETE) SQL statement.
 */
class ParsedDml
{
    /**
     * @param JournalOperationEnum $operation
     * @param string $table Table name without delimiters
     * @param string|null $where The raw WHERE clause (without the WHERE keyword), if any
     * @param array<string, string> $insertValues Map of column => raw value expression (INSERT only)
     */
    public function __construct(
        protected JournalOperationEnum $operation,
        protected string $table,
        protected ?string $where = null,
        protected array $insertValues = []
    ) {
    }

    public function getOperation(): JournalOperationEnum
    {
        return $this->operation;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getWhere(): ?string
    {
        return $this->where;
    }

    /**
     * @return array<string, string>
     */
    public function getInsertValues(): array
    {
        return $this->insertValues;
    }

    /**
     * Resolve the parsed INSERT value expressions against the statement parameters.
     * Named parameters (:name) are replaced by their values; simple quoted/numeric
     * literals are converted; other expressions (functions, etc.) are discarded.
     *
     * @param array|null $params
     * @return array<string, mixed>
     */
    public function resolveInsertValues(?array $params): array
    {
        $result = [];
        foreach ($this->insertValues as $column => $expression) {
            $expression = trim($expression);
            if (preg_match('~^:(?<param>[_\w\d]+)$~', $expression, $matches)) {
                if (is_array($params) && array_key_exists($matches['param'], $params)) {
                    $result[$column] = $params[$matches['param']];
                }
                continue;
            }
            if (is_numeric($expression)) {
                $result[$column] = str_contains($expression, '.') ? (float)$expression : (int)$expression;
                continue;
            }
            if (preg_match("~^'(?<value>[^']*)'$~", $expression, $matches)) {
                $result[$column] = $matches['value'];
                continue;
            }
            if (strcasecmp($expression, 'null') === 0) {
                $result[$column] = null;
            }
            // Any other expression (functions, sub-selects, etc.) cannot be resolved statically
        }
        return $result;
    }
}