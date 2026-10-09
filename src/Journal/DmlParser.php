<?php

namespace ByJG\AnyDataset\Db\Journal;

/**
 * Lightweight parser to identify single-table DML statements
 * (INSERT INTO ... VALUES, UPDATE ... SET, DELETE FROM).
 *
 * It intentionally does NOT parse complex statements (multi-statement SQL,
 * INSERT ... SELECT, multi-table UPDATE, CTEs, etc.) - for those it returns null
 * and the statement will not be journaled.
 */
class DmlParser
{
    protected const TABLE_PATTERN = '(?<table>[`"\[\]\w.]+)';

    /**
     * Parse a SQL statement and return the DML metadata or null when
     * the statement is not a supported single-table DML.
     *
     * @param string $sql
     * @return ParsedDml|null
     */
    public static function parse(string $sql): ?ParsedDml
    {
        $sql = trim(rtrim(trim($sql), ';'));

        // Multi-statement SQL is not supported
        if (str_contains($sql, ';')) {
            return null;
        }

        return self::parseInsert($sql)
            ?? self::parseUpdate($sql)
            ?? self::parseDelete($sql);
    }

    protected static function parseInsert(string $sql): ?ParsedDml
    {
        $pattern = '~^insert\s+into\s+' . self::TABLE_PATTERN . '\s*\((?<columns>[^)]+)\)\s*values\s*\((?<values>.+)\)\s*$~is';
        if (!preg_match($pattern, $sql, $matches)) {
            return null;
        }

        $columns = array_map(
            fn($column) => self::stripDelimiters($column),
            explode(',', $matches['columns'])
        );
        $values = self::splitTopLevel($matches['values']);

        $insertValues = [];
        if (count($columns) === count($values)) {
            $insertValues = array_combine($columns, array_map('trim', $values));
        }

        return new ParsedDml(
            JournalOperationEnum::INSERT,
            self::stripDelimiters($matches['table']),
            null,
            $insertValues
        );
    }

    protected static function parseUpdate(string $sql): ?ParsedDml
    {
        $pattern = '~^update\s+' . self::TABLE_PATTERN . '\s+set\s+.+?(?:\s+where\s+(?<where>.+))?$~is';
        if (!preg_match($pattern, $sql, $matches)) {
            return null;
        }

        return new ParsedDml(
            JournalOperationEnum::UPDATE,
            self::stripDelimiters($matches['table']),
            empty($matches['where']) ? null : trim($matches['where'])
        );
    }

    protected static function parseDelete(string $sql): ?ParsedDml
    {
        $pattern = '~^delete\s+from\s+' . self::TABLE_PATTERN . '(?:\s+where\s+(?<where>.+))?$~is';
        if (!preg_match($pattern, $sql, $matches)) {
            return null;
        }

        return new ParsedDml(
            JournalOperationEnum::DELETE,
            self::stripDelimiters($matches['table']),
            empty($matches['where']) ? null : trim($matches['where'])
        );
    }

    /**
     * Remove identifier delimiters (backticks, double quotes, square brackets) and trim.
     */
    public static function stripDelimiters(string $identifier): string
    {
        return trim(str_replace(['`', '"', '[', ']'], '', $identifier));
    }

    /**
     * Split a comma-separated expression list, ignoring commas inside
     * parentheses and single-quoted strings.
     *
     * @param string $expression
     * @return string[]
     */
    protected static function splitTopLevel(string $expression): array
    {
        $result = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $length = strlen($expression);

        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];

            if ($inString) {
                $current .= $char;
                if ($char === "'") {
                    // Handle escaped quote ('')
                    if ($i + 1 < $length && $expression[$i + 1] === "'") {
                        $current .= $expression[++$i];
                    } else {
                        $inString = false;
                    }
                }
                continue;
            }

            switch ($char) {
                case "'":
                    $inString = true;
                    $current .= $char;
                    break;
                case '(':
                    $depth++;
                    $current .= $char;
                    break;
                case ')':
                    $depth--;
                    $current .= $char;
                    break;
                case ',':
                    if ($depth === 0) {
                        $result[] = $current;
                        $current = '';
                    } else {
                        $current .= $char;
                    }
                    break;
                default:
                    $current .= $char;
            }
        }

        if (trim($current) !== '') {
            $result[] = $current;
        }

        return $result;
    }
}