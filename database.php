<?php

/**
 * Database Connection Class for Prime Cargo Limited
 * Handles secure database connections using PDO
 */

class Database
{
    private $host = 'localhost';
    private $db_name = 'prime_cargo_db';
    private $username = 'root';
    private $password = '';
    private $conn;

    /**
     * Get database connection
     * @return PDO|null
     */
    public function getConnection()
    {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]
            );
        } catch (PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            return null;
        }

        return $this->conn;
    }

    /**
     * Close database connection
     */
    public function closeConnection()
    {
        $this->conn = null;
    }

    /**
     * Test database connection
     * @return bool
     */
    public function testConnection()
    {
        try {
            $conn = $this->getConnection();
            if ($conn) {
                $conn->query("SELECT 1");
                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
}
