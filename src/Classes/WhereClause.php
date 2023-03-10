<?php

namespace PDO_Connection\Classes;

use PDO_Connection\Classes\ParamBindObject;
use PDO_Connection\Statics\OPERATOR;

use PDO;

class WhereClause
{
    public string $column;
    private mixed $value;
    private string $operator;
    private array $boundParams = [];

    public function __construct(string $column, string $operator = OPERATOR::EQUALS, mixed $value = null)
    {
        $this->column = $column;
        $this->value = $value;
        $this->operator = $operator;

        switch ($this->operator) {
            case OPERATOR::IS_NULL:
            case OPERATOR::IS_NOT_NULL:
                $this->value = "";
                break;
            case OPERATOR::EQUALS:
            case OPERATOR::NOT_EQUALS:
            case OPERATOR::GREATER_THAN:
            case OPERATOR::GREATER_THAN_OR_EQUAL_TO:
            case OPERATOR::LESS_THAN:
            case OPERATOR::LESS_THAN_OR_EQUAL_TO:
            case OPERATOR::LIKE:
            case OPERATOR::NOT_LIKE:
                $this->boundParams = [new ParamBindObject(":xx" . $this->column, $this->value, 2)];
                $this->value = " " . "`:xx" . $this->column . "`";
                break;
            case OPERATOR::IN:
            case OPERATOR::NOT_IN:
                if (!is_array($this->value)) {
                    throw new \Exception("The value for the '" . $this->operator . "' operator must be an array.");
                }
                $this->value = " (" . implode(", ", array_map(function ($value, $index) {
                    $this->boundParams[] = new ParamBindObject(":" . str_repeat("x", $index) . $this->column, $value, $index);
                    return "`" . ":" . str_repeat("x", $index) . $this->column . "`";
                }, $this->value)) . ")";
                break;
            case OPERATOR::BETWEEN:
            case OPERATOR::NOT_BETWEEN:
                if (!is_array($this->value)) {
                    throw new \Exception("The value for the '" . $this->operator . "' operator must be an array.");
                }
                if (count($this->value) !== 2) {
                    throw new \Exception("The value for the '" . $this->operator . "' operator must be an array with 2 values.");
                }
                $this->value = " " . implode(" AND ", array_map(function ($value, $index) {
                    $this->boundParams[] = new ParamBindObject(":" . str_repeat("x", $index) . $this->column, $value, $index);
                    return "`" . ":" . str_repeat("x", $index) . $this->column . "`";
                }, $this->value));
                break;
            default:
                throw new \Exception("The operator '" . $this->operator . "' is not supported.");
                break;
        }
    }

    public function getClause(): string
    {
        return $this->column . " " . $this->operator . $this->value;
    }

    public function getBoundParams(): array
    {
        return $this->boundParams;
    }
}
