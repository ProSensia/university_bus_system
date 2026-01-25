<?php
require_once __DIR__ . '/config.php';

class Database {
    private $pdo;
    private $stmt;
    private static $instance = null;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];

            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Ensure logs directory exists
            if (!is_dir(BASE_PATH . '/logs')) {
                mkdir(BASE_PATH . '/logs', 0755, true);
            }

        } catch (PDOException $e) {
            $this->logError('DATABASE_CONNECTION', $e->getMessage());
            http_response_code(500);
            exit('Database connection failed.');
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function prepare($sql) {
        $this->stmt = $this->pdo->prepare($sql);
        return $this;
    }

    public function bind($param, $value, $type = null) {
        if ($type === null) {
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
        }
        $this->stmt->bindValue($param, $value, $type);
        return $this;
    }

    public function execute() {
        try {
            return $this->stmt->execute();
        } catch (PDOException $e) {
            $this->logError('QUERY_EXECUTION', $e->getMessage());
            return false;
        }
    }

    public function single() {
        $this->execute();
        return $this->stmt->fetch();
    }

    public function resultSet() {
        $this->execute();
        return $this->stmt->fetchAll();
    }

    public function rowCount() {
        return $this->stmt->rowCount();
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    private function logError($type, $message) {
        $log = "[" . date('Y-m-d H:i:s') . "] [$type] " . ($_SERVER['REMOTE_ADDR'] ?? 'CLI') . " - $message\n";
        file_put_contents(BASE_PATH . '/logs/db_errors.log', $log, FILE_APPEND);
    }

    public function __destruct() {
        $this->stmt = null;
        $this->pdo = null;
    }
}

$db = Database::getInstance();
