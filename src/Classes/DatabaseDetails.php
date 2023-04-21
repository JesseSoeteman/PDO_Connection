<?php

namespace PDO_Connection\Classes;

/**
 * DatabaseDetails
 * Holds the database details such as the DSN, username, password and database name.
 *
 * 
 * @version 1.0.0
 * @since   29-12-2022
 */
class DatabaseDetails {
    /**
     * The DSN to use.
     * 
     * @var string
     */
    public string $dsn;
    /**
     * The username to use.
     * 
     * @var string
     */
    public string $username;
    /**
     * The password to use.
     * 
     * @var string
     */
    public string $password;
    /**
     * The database name to use.
     * 
     * @var string
     */
    public string $database;

    /**
     * Constructor
     * 
     * @param string $unix_socket The unix socket to use.
     * @param string $database The database name.
     * @param string $username The username to use.
     * @param string $password The password to use.
     */
    public function __construct(string $unix_socket, string $database, string $username, string $password) {
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;

        $this->dsn = sprintf("mysql:dbname=%s;unix_socket=%s", $database, $unix_socket);
    }
}