<?php
namespace App\Database;

use PDO;

class DB {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $config = require __DIR__ . '/../../config/config.php';
        $this->pdo = new PDO(
            "pgsql:host={$config['host']};dbname={$config['dbname']}",
            $config['user'], $config['pass']
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new DB();
        }
        return self::$instance;
    }

    public function pdo(): PDO {
	return $this->pdo;
    }

    public function insert(string $table, array $data): bool {
        $keys = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
        $stmt = $this->pdo->prepare("INSERT INTO $table ($keys) VALUES ($placeholders)");
        return $stmt->execute($data);
    }

    public function selectOne(string $query, array $params = []): ?array {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function selectAll(string $query, array $params = []): array {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function execute(string $query, array $params = []): bool {
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute($params);
    }
}

