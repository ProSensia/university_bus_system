<?php
require_once __DIR__ . '/config.php';

class Database {
    private $pdo;
    private $stmt;
    private static $instance = null;

    // Singleton pattern for database connection
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::MYSQL_ATTR_SSL_CA => __DIR__ . '/certs/ca-cert.pem', // SSL certificate path
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
            ];

            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Create logs directory if not exists
            if (!is_dir(BASE_PATH . '/logs')) {
                mkdir(BASE_PATH . '/logs', 0755, true);
            }
            
        } catch (PDOException $e) {
            $this->logError('DATABASE_CONNECTION', $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    // Prepare statement
    public function prepare($sql) {
        $this->stmt = $this->pdo->prepare($sql);
        return $this;
    }

    // Bind parameters
    public function bind($param, $value, $type = null) {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
        return $this;
    }

    // Execute statement
    public function execute() {
        try {
            return $this->stmt->execute();
        } catch (PDOException $e) {
            $this->logError('QUERY_EXECUTION', $e->getMessage());
            return false;
        }
    }

    // Get single record
    public function single() {
        $this->execute();
        return $this->stmt->fetch();
    }

    // Get all records
    public function resultSet() {
        $this->execute();
        return $this->stmt->fetchAll();
    }

    // Get row count
    public function rowCount() {
        return $this->stmt->rowCount();
    }

    // Last insert ID
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    // Begin transaction
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    // Commit transaction
    public function commit() {
        return $this->pdo->commit();
    }

    // Rollback transaction
    public function rollBack() {
        return $this->pdo->rollBack();
    }

    // Check if table exists
    public function tableExists($table) {
        try {
            $result = $this->pdo->query("SELECT 1 FROM $table LIMIT 1");
            return $result !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    // Sanitize input
    public function sanitize($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }

    // Log errors
    private function logError($type, $message) {
        $log = "[" . date('Y-m-d H:i:s') . "] [$type] " . $_SERVER['REMOTE_ADDR'] . " - $message\n";
        file_put_contents(BASE_PATH . '/logs/db_errors.log', $log, FILE_APPEND);
    }

    // Close connection
    public function __destruct() {
        $this->stmt = null;
        $this->pdo = null;
    }
}

// Create global database instance
$db = Database::getInstance();
?>