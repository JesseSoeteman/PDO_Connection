<?php

namespace PDO_Connection\Classes;

class DatabaseDetails {
    public string $dsn;
    public string $username;
    public string $password;

    public function __construct(string $host, string $database, string $username, string $password) {
        $this->username = $username;
        $this->password = $password;

        $this->dsn = "mysql:host=" . $host . ";dbname=" . $database;
    }
}