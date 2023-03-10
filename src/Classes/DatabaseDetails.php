<?php

namespace PDO_Connection\Classes;

class DatabaseDetails {
    public string $dsn;
    public string $username;
    public string $password;

    public function __construct(string $unix_socket, string $database, string $username, string $password) {
        $this->username = $username;
        $this->password = $password;

        $this->dsn = sprintf("mysql:dbname=%s;unix_socket=%s", $database, $unix_socket);
    }
}