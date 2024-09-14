<?php

namespace ByJG\AnyDataset\Db\Helpers;

use ByJG\Util\Uri;

/**
 * Class to create and manipulate Several Data Types
 *
 */
class SqlBind
{

    /**
     * Each provider have your own model for pass parameter.
     * This method define how each provider name define the parameters
     *
     * The default is ":_"
     *
     * The symbol "_" will be replaced by the parameter name
     *
     * @param Uri $connData
     * @return string
     */
    public static function getParamModel(Uri $connData): string
    {
        return $connData->getQueryPart("parammodel") ?? ":_";
    }

    /**
     * Transform generic parameters [[PARAM]] in a parameter recognized by the provider
     * name based on current DbParameter array.
     *
     * @param Uri $connData
     * @param string $sql
     * @param array|null $params
     * @return array An array with the adjusted SQL and PARAMs
     */
    public static function parseSQL(Uri $connData, string $sql, array $params = null): array
    {
        $paramSubstName = SqlBind::getParamModel($connData);

        $sqlAlter = preg_replace("~'.*?((\\\\'|'').*?)*'~", "", $sql);
        preg_match_all(
            "/(?<deliStart>\\[\\[|:)(?<param>[\\w\\d]+)(?<deliEnd>\\]\\]|[^\\d\\w]|$)/",
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
                        "/\\[\\[$paramName\\]\\]/",
                        "/:$paramName([^\\d\\w]|$)/"
                    ],
                    [
                        "null",
                        "null$2"
                    ],
                    $sql
                );
                continue;
            }

            $usedParams[$paramName] = isset($params[$paramName]) ? $params[$paramName] : null;
            $dbArg = str_replace("_", SqlBind::keyAdj($paramName), $paramSubstName);

            $count = 0;
            $sql = preg_replace(
                [
                    "/\\[\\[$paramName\\]\\]/",
                    "/:$paramName([^\\w\\d]|$)/",
                ],
                [
                    $dbArg . '',
                    $dbArg . '$1',
                ],
                $sql,
                -1,
                $count
            );
        }

        return [$sql, $usedParams];
    }

    public static function keyAdj(string $key): string
    {
        return str_replace(".", "_", $key);
    }
}
