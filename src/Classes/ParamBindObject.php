<?php

namespace PDO_Connection\Classes;

use PDO;
// use PDO

/**
 * ParamBindObject
 * 
 * This class is used to bind parameters to a query.
 * 
 * @author  Jesse Soeteman
 * @version 1.0.0
 * @since   29-12-2022
 */
class ParamBindObject
{
    /**
     * @var string $param The parameter to bind.
     */
    public string $param;
    /**
     * @var $value The value to bind to the parameter.
     */
    public $value;
    /**
     * @var int $type The type of the value.
     */
    public int $type;
    /**
     * @var int $idCount A counter to keep track of how many characters are used to bind the parameter.
     */
    public int $idCount;


    /**
     * Constructor
     * 
     * @param string $param The parameter to bind.
     * @param $value The value to bind to the parameter.
     * @param int $idCount A counter to keep track of how many characters are used to bind the parameter.
     */
    public function __construct($param, $value, $idCount = 0)
    {
        if (!isset($GLOBALS["PDO_Connection_PDO"])) {
            throw new \Exception("The PDO object is not set.");
        }

        $this->param = $param;
        $this->idCount = $idCount;

        $this->value = $value;

        $type = gettype($value);

        switch ($type) {
            case "boolean":
                $this->type = PDO::PARAM_BOOL;
                break;
            case "integer":
                $this->type = PDO::PARAM_INT;
                break;
            case "string":
                $this->type = PDO::PARAM_STR;
                break;
            case "float":
                $this->type = PDO::PARAM_STR;
                break;
            default:
                $this->type = PDO::PARAM_STR;
                break;
        }
    }
}
