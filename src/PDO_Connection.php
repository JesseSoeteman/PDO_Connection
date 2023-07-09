<?php

namespace PDO_Connection;

use PDO;
use Exception;
use PDO_Connection\Classes\DatabaseDetails;
use PDO_Connection\Classes\ParamBindObject;
use PDO_Connection\Classes\WhereClause;
use PDO_Connection\Statics\OPERATOR;
use PDO_Connection\Statics\LOGIC_OP;

/**
 * PDO_Connection
 *
 * 
 * @author  Jesse Soeteman
 * @version 1.0.0
 * @since   29-12-2022
 */
class PDO_Connection
{

    private PDO | null $db;
    private DatabaseDetails $details;
    private bool $pause = false;

    /**
     * Constructor
     * 
     * @param DatabaseDetails $details The database details to use.
     */
    public function __construct(DatabaseDetails $details)
    {
        // Set the database details
        $this->details = $details;
        $this->startConnection();
    }

    /**
     * Select Query, returns an array of rows.
     *  
     * @param string $table The table to select from.
     * @param array $columns The columns to select.
     * @param array $wheres The where clauses to use. (WhereClause)
     * 
     * @return array The rows that were selected.
     * 
     * @throws Exception
     */
    public function select(string $table, array $columns, array $wheres = []): array
    {
        // Create the sql statement
        $sql = "SELECT " . implode(" ,", $columns) . " FROM {$table}";

        $params = [];

        // Check if there are any where clauses to use and add them to the sql statement
        if (count($wheres) > 0) {
            $getWhere = $this->getWhereClauseString($wheres);
            $sql .= $getWhere[0];
            $params = array_merge($params, $getWhere[1]);
        }

        // Execute the statement
        $result = $this->executeStatement($sql, $params);

        // Check if the statement failed
        if ($result === false) {
            $this->checkError([false, "Error while executing statement."]);
        }

        // Decode html special chars
        $result = array_map(function ($row) {
            return array_map(function ($value) {
                // $result = htmlspecialchars_decode($value);
                $result = html_entity_decode($value);
                // Turn the value into an integer if it is one
                if (is_numeric($result)) {
                    $result = intval($result);
                }
                // Turn the value into a float if it is one
                if (is_float($result)) {
                    $result = floatval($result);
                }   
                // Return the result
                return $result;
            }, $row);
        }, $result);

        // Return the result
        return $this->checkError([true, $result]);
    }

    /**
     * Insert Query, inserts a row into the database.
     * 
     * @param string $table The table to insert into.
     * @param array $params The parameters to bind to the query. (ParamBindObject)
     * 
     */
    public function insert(string $table, array $params)
    {
        // Create the sql statement
        $sql = "INSERT INTO {$table} (" . implode(" ,", array_map(function ($param) {
            return $param->param;
        }, $params)) . ") VALUES (" . implode(" ,", array_map(function ($param) {
            return ":" . str_repeat("x", $param->idCount) . $param->param;
        }, $params)) . ")";

        // Execute the statement
        $result = $this->executeStatement($sql, $params);

        // Check if the statement failed
        if ($result === false) {
            $this->checkError([false, "Error while executing statement."]);
        }

        // Return the result
        return $this->checkError([true, $result]);
    }

    /**
     * Update Query, updates a row or rows in the database.
     * 
     * @param string $table The table to update.
     * @param array $params The parameters to bind to the query. (ParamBindObject)
     * @param array $wheres The where conditions. (WhereClause)
     */
    public function update(string $table, array $params, array $wheres = [])
    {
        // Check if there are any rows to update
        $select = $this->select($table, ["*"], $wheres);

        // Stop the script if there are no rows to update
        if (count($select) === 0) {
            $this->checkError([false, "No rows found."]);
        }

        // Create the sql statement
        $sql = "UPDATE {$table} SET " . implode(" ,", array_map(function ($param) {
            return $param->param . " = :" . str_repeat("x", $param->idCount) . $param->param;
        }, $params));

        // Check if there are any where clauses to use and add them to the sql statement
        if (count($wheres) > 0) {
            $getWhere = $this->getWhereClauseString($wheres);
            $sql .= $getWhere[0];
            $params = array_merge($params, $getWhere[1]);
        }

        // Execute the statement
        $result = $this->executeStatement($sql, $params);

        // Check if the statement failed
        if ($result === false) {
            $this->checkError([false, "Error while executing statement."]);
        }

        // Return the result
        return $this->checkError([true, $result]);
    }

    /**
     * Delete Query, deletes a row from the database.
     * 
     * Executes a delete query on the database.
     * 
     * @param string $table The table to delete from.
     * @param array $wheres The where conditions. (WhereClause)
     */
    public function delete(string $table, array $wheres = [], int $minimumRowsToDelete = 1)
    {
        // Check if there are any rows to delete
        $select = $this->select($table, ["*"], $wheres);

        // Stop the script if there are no rows to delete and the minimum rows to delete is not 0
        if (count($select) < $minimumRowsToDelete) {
            $this->checkError([false, "No rows found."]);
        }

        // Create the sql statement
        $params = [];
        $sql = "DELETE FROM {$table}";

        // Check if there are any where clauses to use and add them to the sql statement
        if (count($wheres) > 0) {
            $getWhere = $this->getWhereClauseString($wheres);
            $sql .= $getWhere[0];
            $params = array_merge($params, $getWhere[1]);
        }

        // If this fails, it will throw an exception.
        $this->executeStatement($sql, $params, false);

        // Return the result
        return $this->checkError([true, true]);
    }

    /**
     * Get Last Insert Row ID
     * 
     * Returns the last inserted row ID.
     * 
     * @return int The last inserted row ID.
     */
    public function lastInsertRowID(): int
    {
        // Return the last inserted row ID
        return $this->db->lastInsertId();
    }

    /**
     * Execute Statement
     * 
     * Executes a statement on the database. With this function you can execute any query.
     * 
     * @param string $query The query to execute.
     * @param array $params The parameters to bind to the query. (ParamBindObject)
     * @param bool $needsFetch If the query needs to be fetched.
     */
    public function executeStatement($query = "", $params = [], $needsFetch = true)
    {
        $stmt = null;

        // Prepare the statement
        if (!$stmt = $this->db->prepare($query)) {
            $this->checkError([false, "Failed to prepare statement."]);
        }

        // Bind the parameters
        foreach ($params as &$param) {
            if (!$param instanceof ParamBindObject) {
                $this->checkError([false, "ParamBindObject expected."]);
            }

            $binding = $stmt->bindValue($param->param, $param->value, $param->type);
            if (!$binding) {
                $this->checkError([false, "Failed to bind value."]);
            }
        }

        // Execute the statement
        $result = $stmt->execute();

        // Check if the statement failed
        if (!$result) {
            $this->checkError([false, "Failed to execute statement."]);
        }

        // Check if the statement needs to be fetched
        if (!$needsFetch) {
            return true;
        }

        // Fetch the result
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if the statement failed
        if ($result === false) {
            $this->checkError([false, "Failed to fetch result."]);
        }

        // Return the result
        return $result;
    }

    /**
     * pausePDO
     * 
     * Pauses the PDO connection.
     */
    public function pausePDO()
    {
        // Set de PDO to null to pause the connection
        $this->db = null;
        // Set the pause variable to true
        $this->pause = true;
    }

    /**
     * Resume PDO
     * 
     * Resumes the PDO connection.
     * 
     * @throws Exception
     */
    public function resumePDO()
    {
        // Resume the connection with the databasedetails
        $this->startConnection();
        // Set the pause variable to false
        $this->pause = false;
    }

    /**
     * startConnection
     * 
     * Starts the connection to the database.
     * 
     * @throws Exception
     */
    private function startConnection()
    {
        // Try to start a PDO connection with the database details
        try {
            $this->db = new PDO($this->details->dsn, $this->details->username, $this->details->password);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            $this->checkError([false, "Data base connection failed: " . $e->getMessage()]);
        }
    }

    /**
     * Get Where Clause String
     * 
     * Gets the where clause string and parameters.
     * 
     * @param array $wheres The where conditions. (WhereClause) [WhereClause, operator, WhereClause, operator, ...]
     * 
     * @return array The where clause string and parameters. [string, params]
     * 
     * @throws Exception
     */
    private function getWhereClauseString(array $wheres)
    {
        $params = [];
        // Create the where part of the sql statement
        $sql = " WHERE " . implode(" ", array_map(function ($where, $index) use (&$params) {
            // Check if the index is even or odd (even = where clause, odd = operator)
            if ($index % 2 === 0) {
                if (!$where instanceof WhereClause) {
                    $this->checkError([false, "WhereClause expected."]);
                }
                $params = array_merge($params, $where->getBoundParams());
                return $where->getClause();
            } else {
                if (!in_array($where, [LOGIC_OP::AND, LOGIC_OP::OR])) {
                    $this->checkError([false, "Invalid operator."]);
                }
                return $where;
            }
        }, $wheres, array_keys($wheres)));
        // return the sql statement and the parameters
        return [$sql, $params];
    }

    /**
     * Check Error
     * 
     * Checks if an error occured.
     * Throws an exception if an error occured.
     * 
     * @param array $result The result to check.
     */
    private function checkError($result)
    {
        // if the result is false, throw an exception
        if ($result[0] == false) {
            throw new Exception($result[1]);
        }
        // otherwise return the result
        return $result[1];
    }
}
