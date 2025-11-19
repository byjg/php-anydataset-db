<?php

namespace ByJG\AnyDataset\Db;

use ByJG\Util\Uri;

/**
 * Utility class to bind and process SQL parameters
 *
 * Handles parameter placeholder replacement and parameter binding
 * for different database drivers.
 */
class ParameterBinder
{

    /**
     * Get the parameter placeholder pattern for the current connection
     *
     * Each database provider has its own model for passing parameters.
     * This method defines how each provider names and defines parameters.
     *
     * The default pattern is ":_"
     * The symbol "_" will be replaced by the parameter name
     *
     * @param Uri $connData Connection URI containing configuration
     * @return string The parameter placeholder pattern
     */
    public static function getParameterPlaceholderPattern(Uri $connData): string
    {
        return $connData->getQueryPart("parammodel") ?? ":_";
    }

    /**
     * Prepare parameter bindings for SQL execution
     *
     * Transforms generic parameters (e.g., :param) into parameters recognized
     * by the specific database provider, based on the connection configuration.
     *
     * @param Uri $connData Connection URI containing configuration
     * @param string $sql The SQL query with parameter placeholders
     * @param array|null $params The parameters to bind
     * @return array An array with [adjusted SQL, used parameters]
     */
    public static function prepareParameterBindings(Uri $connData, string $sql, ?array $params = null): array
    {
        $paramSubstName = self::getParameterPlaceholderPattern($connData);

        $sqlAlter = preg_replace("~'.*?((\\\\'|'').*?)*'~", "", $sql);
        preg_match_all(
            "/(?<!:):(?<param>[_\\w\\d]+)\b/",
            $sqlAlter,
            $matches
        );

        $usedParams = [];

        if (is_null($params)) {
            $params = [];
        }

        foreach ($matches['param'] as $paramName) {
            if (!array_key_exists($paramName, $params)) {
                // Remove NON DEFINED parameters
                $sql = preg_replace(
                    [
                        "/(?<!:):$paramName\b/"
                    ],
                    [
                        "null"
                    ],
                    $sql
                );
                continue;
            }

            $usedParams[$paramName] = $params[$paramName] ?? null;
            $dbArg = str_replace("_", self::sanitizeParameterKey($paramName), $paramSubstName);

            $count = 0;
            $sql = preg_replace(
                [
                    "/(?<!:):$paramName\b/",
                ],
                [
                    $dbArg,
                ],
                $sql,
                -1,
                $count
            );
        }

        return [$sql, $usedParams];
    }

    /**
     * Sanitize a parameter key for safe use in SQL
     *
     * Replaces potentially problematic characters (like dots) with underscores
     *
     * @param string $key The parameter key to sanitize
     * @return string The sanitized parameter key
     */
    public static function sanitizeParameterKey(string $key): string
    {
        return str_replace(".", "_", $key);
    }
}
