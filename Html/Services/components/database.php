<?php
/**
 * Conexão com o banco de dados (Classe + Funções)
 */
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dbPath = __DIR__ . '/../../../database/TesteOlga.db';
            $this->connection = new PDO('sqlite:' . $dbPath);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Erro na conexão com o banco de dados: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
}


?>
