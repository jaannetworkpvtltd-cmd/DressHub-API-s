<?php

class Database {
    private $conn;

    public function connect() {
        $this->conn = null;

        try {
            // Use the same credentials from connect.php
            require __DIR__ . '/../connect.php';
            $this->conn = $conn;
        } catch (PDOException $e) {
            echo 'Connection Error: ' . $e->getMessage();
        }

        return $this->conn;
    }
}
