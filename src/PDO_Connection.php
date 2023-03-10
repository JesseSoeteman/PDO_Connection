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
        $this->checkTableAndColumns($table, $columns);

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

        $returnArray = [];

        while ($row = $result) {
            $returnArray[] = $row;
        }

        return $this->checkError([true, $returnArray]);
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
        $this->checkTableAndColumns($table, array_map(function ($param) {
            if (!$param instanceof ParamBindObject) {
                $this->checkError([false, "ParamBindObject expected."]);
            }
            return $param->param;
        }, $params));

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
        $this->checkTableAndColumns($table, array_merge(array_map(function ($param) {
            if (!$param instanceof ParamBindObject) {
                $this->checkError([false, "ParamBindObject expected."]);
            }
            return $param->param;
        }, $params), array_map(function ($where) {
            if (!$where instanceof WhereClause) {
                $this->checkError([false, "WhereClause expected."]);
            }
            return $where->column;
        }, $wheres)));

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
        $this->checkTableAndColumns($table, array_map(function ($where) {
            if (!$where instanceof WhereClause) {
                $this->checkError([false, "WhereClause expected."]);
            }
            return $where->column;
        }, $wheres));

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

        $result = $this->executeStatement($sql, $params);

        if ($result === false) {
            $this->checkError([false, "Error while executing statement."]);
        }

        return $this->checkError([true, $result]);
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
     */
    public function executeStatement($query = "", $params = [])
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

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$result) {
            $this->checkError([false, "Failed to fetch result."]);
        }

        return $result;
    }

    /**
     * Check Table And Columns
     * 
     * Checks if a table and columns exist. Throws an exception if the table or columns do not exist.
     * 
     * @param string $table The table to check.
     * @param array $columns The columns to check.
     * 
     * @return void
     */
    public function checkTableAndColumns(string $table, array $columns = ["*"]): void
    {
        $tableExists = $this->executeStatement("SELECT name FROM sqlite_master WHERE type='table' AND name=:table_name;", [new ParamBindObject($this->db, "table_name", $table)]);

        if ($tableExists == false || count($tableExists) == 0) {
            $this->checkError([false, "Table does not exist. Table: {$table}"]);
        }

        if (count($columns) == 0 || $columns[0] == "*") {
            return;
        }

        $tableColumns = $this->executeStatement("PRAGMA table_info({$table});");

        $tableColumnsArray = [];

        foreach ($tableColumns as $row) {
            $tableColumnsArray[] = $row["name"];
        }

        foreach ($columns as $column) {
            if (!in_array($column, $tableColumnsArray)) {
                $this->checkError([false, "Column does not exist. Column: {$column}"]);
            }
        }
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
