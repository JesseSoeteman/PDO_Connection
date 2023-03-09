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

    private PDO $db;
    private DatabaseDetails $details;

    /**
     * Constructor
     * 
     * 
     */
    public function __construct(DatabaseDetails $details)
    {
        try {
            $this->db = new PDO($details->dsn, $details->username, $details->password);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->details = $details;
            $GLOBALS["PDO_Connection_PDO"] = &$this->db;
        } catch (Exception $e) {
            $this->checkError([false, "Data base connection failed: " . $e->getMessage()]);
        }
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
        $sql = "SELECT " . implode(" ,", $columns) . " FROM {$table}";

        $params = [];

        if (count($wheres) > 0) {
            $getWhere = $this->getWhereClauseString($wheres);
            $sql .= $getWhere[0];
            $params = array_merge($params, $getWhere[1]);
        }
        
        $result = $this->executeStatement($sql, $params);

        if ($result === false) {
            $this->checkError([false, "Error while executing statement."]);
        }

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
        $sql = "INSERT INTO {$table} (" . implode(" ,", array_map(function ($param) {
            return $param->param;
        }, $params)) . ") VALUES (" . implode(" ,", array_map(function ($param) {
            return str_repeat(":", $param->idCount) . $param->param;
        }, $params)) . ")";

        $result = $this->executeStatement($sql, $params);

        if ($result === false) {
            $this->checkError([false, "Error while executing statement."]);
        }

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
        var_dump($table);
        var_dump($params);
        var_dump($wheres);
        
        $select = $this->select($table, ["*"], $wheres);

        if (count($select) === 0) {
            $this->checkError([false, "No rows found."]);
        }

        $sql = "UPDATE {$table} SET " . implode(" ,", array_map(function ($param) {
            return $param->param . " = " . str_repeat(":", $param->idCount) . $param->param;
        }, $params));

        if (count($wheres) > 0) {
            $getWhere = $this->getWhereClauseString($wheres);
            $sql .= $getWhere[0];
            $params = array_merge($params, $getWhere[1]);
        }

        var_dump($sql);
        var_dump($params);

        $result = $this->executeStatement($sql, $params);

        if ($result === false) {
            $this->checkError([false, "Error while executing statement."]);
        }

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
    public function delete(string $table, array $wheres = [])
    {
        $select = $this->select($table, ["*"], $wheres);

        if (count($select) === 0) {
            $this->checkError([false, "No rows found."]);
        }
        
        $params = [];
        $sql = "DELETE FROM {$table}";

        if (count($wheres) > 0) {
            $getWhere = $this->getWhereClauseString($wheres);
            $sql .= $getWhere[0];
            $params = array_merge($params, $getWhere[1]);
        }

        // If this fails, it will throw an exception.
        $this->executeStatement($sql, $params, false);

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

        if (!$stmt = $this->db->prepare($query)) {
            $this->checkError([false, "Failed to prepare statement."]);
        }

        foreach ($params as &$param) {

            if (!$param instanceof ParamBindObject) {
                $this->checkError([false, "ParamBindObject expected."]);
            }

            $idCountString = str_repeat(":", $param->idCount);
            $stmt->bindValue($idCountString . $param->param, $param->value, $param->type);
            if (!$stmt) {
                $this->checkError([false, "Failed to bind value."]);
            }
        }

        
        $result = $stmt->execute();
        
        if (!$result) {
            $this->checkError([false, "Failed to execute statement."]);
        }

        if (!$needsFetch) {
            return true;
        }
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($result === false) {
            $this->checkError([false, "Failed to fetch result."]);
        }

        return $result;
    }

    /**
     * Get Where Clause String
     * 
     * Gets the where clause string and parameters.
     * 
     * @param array $wheres The where conditions. (WhereClause)
     * 
     * @return array The where clause string and parameters. [string, params]
     * 
     * @throws Exception
     */
    private function getWhereClauseString(array $wheres)
    {
        $params = [];
        $sql = " WHERE " . implode(" ", array_map(function ($where, $index) use (&$params) {
            if ($index % 2 === 0) {
                if (!$where instanceof WhereClause) {
                    $this->checkError([false, "WhereClause expected."]);
                }
                $params = $where->getBoundParams();
                return $where->getClause();
            } else {
                if (!in_array($where, [LOGIC_OP::AND, LOGIC_OP::OR])) {
                    $this->checkError([false, "Invalid operator."]);
                }
                return $where;
            }
        }, $wheres, array_keys($wheres)));
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
        if ($result[0] == false) {
            throw new Exception($result[1]);
        }
        return $result[1];
    }
}
