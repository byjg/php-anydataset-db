<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Enum\Relation;
use ByJG\AnyDataset\Core\IteratorFilterFormatter;
use InvalidArgumentException;
use Override;

class IteratorFilterSqlFormatter extends IteratorFilterFormatter
{
    private array $params = [];
    private ?string $tableName = null;
    private string $returnFields = "*";

    public function __construct(?string $tableName = null, string $returnFields = "*")
    {
        $this->tableName = $tableName;
        $this->returnFields = $returnFields;
    }

    public function setTableName(?string $tableName): self
    {
        $this->tableName = $tableName;
        return $this;
    }

    public function setReturnFields(string $returnFields): self
    {
        $this->returnFields = $returnFields;
        return $this;
    }

    #[Override]
    public function format(array $filters, array &$params = []): string
    {
        $params = array();

        $sql = "select @@returnFields from @@tableName ";
        $sqlFilter = $this->getFilter($filters, $params);
        if ($sqlFilter != "") {
            $sql .= " where @@sqlFilter ";
        }

        return self::createSafeSQL(
            $sql,
            [
                "@@returnFields" => $this->returnFields,
                "@@tableName" => $this->tableName,
                "@@sqlFilter" => $sqlFilter
            ]
        );
    }

    #[Override]
    public function getFilter(array $filters, array &$param): string
    {
        $this->params = &$param;
        return parent::getFilter($filters, $param);
    }

    #[Override]
    public function getRelation(string $name, Relation $relation, mixed $value): string
    {
        $paramName = $name;
        $counter = 0;
        while (array_key_exists($paramName, $this->params)) {
            $paramName = $name . ($counter++);
        }

        $paramStr = function ($paramName, $value) {
            $this->params[$paramName] = trim($value);
            $result = ":$paramName";
            if (is_object($value)) {
                unset($this->params[$paramName]);
                $result = $value->__toString();
            }
            return $result;
        };

        switch ($relation) {
            case Relation::EQUAL:
                return " $name = " . $paramStr($paramName, $value) . ' ';
            case Relation::GREATER_THAN:
                return " $name > " . $paramStr($paramName, $value) . ' ';
            case Relation::LESS_THAN:
                return " $name < " . $paramStr($paramName, $value) . ' ';
            case Relation::GREATER_OR_EQUAL_THAN:
                return " $name >= " . $paramStr($paramName, $value) . ' ';
            case Relation::LESS_OR_EQUAL_THAN:
                return " $name <= " . $paramStr($paramName, $value) . ' ';
            case Relation::NOT_EQUAL:
                return " $name <> " . $paramStr($paramName, $value) . ' ';
            case Relation::STARTS_WITH:
                $strValue = is_array($value) ? implode(',', $value) : (string)$value;
                return " $name  like  " . $paramStr($paramName, $strValue . "%") . ' ';
            case Relation::CONTAINS:
                $strValue = is_array($value) ? implode(',', $value) : (string)$value;
                return " $name  like  " . $paramStr($paramName, "%" . $strValue . "%") . ' ';
            case Relation::IN:
                if (!is_array($value)) {
                    $value = [$value];
                }
                $placeholders = implode(', ', array_map(fn($v, $i) => ":$paramName$i", $value, array_keys($value)));
                foreach ($value as $i => $v) {
                    $this->params["$paramName$i"] = $v;
                }
                return " $name IN ($placeholders) ";
            case Relation::NOT_IN:
                if (!is_array($value)) {
                    $value = [$value];
                }
                $placeholders = implode(', ', array_map(fn($v, $i) => ":$paramName$i", $value, array_keys($value)));
                foreach ($value as $i => $v) {
                    $this->params["$paramName$i"] = $v;
                }
                return " $name NOT IN ($placeholders) ";
            case Relation::IS_NULL:
                return " $name IS NULL ";
            case Relation::IS_NOT_NULL:
                return " $name IS NOT NULL ";
        }

        throw new InvalidArgumentException("Invalid relation type");
    }

    public static function createSafeSQL(string $sql, array $list): string
    {
        return str_replace(array_keys($list), array_values($list), $sql);
    }
}
