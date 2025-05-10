<?php
namespace App\Services;

use App\Database\DB;
use PDO;

class UserService {
	
    private $db;
    
    public function __construct() {
	$this->db = DB::getInstance();
    }

    public function findUserByUsername(string $username): ?array {
    	return $this->db->selectOne(
	    "SELECT * FROM users WHERE username = :username LIMIT 1",
	    ['username' => $username]
	);
    }

    public function createUser(string $username, array $owners, string $password): bool {
        $pdo = $this->db->pdo();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$username, $passwordHash]);
            $userId = $pdo->lastInsertId();

            // Check for already claimed owners
            $placeholders = implode(',', array_fill(0, count($owners), '?'));
            $stmtCheck = $pdo->prepare("SELECT owner FROM github_ownerships WHERE owner IN ($placeholders)");
            $stmtCheck->execute($owners);
            $existing = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($existing)) {
                throw new \Exception("These GitHub owners are already claimed: " . implode(', ', $existing));
            }

            // Insert ownerships
            $stmtInsert = $pdo->prepare("INSERT INTO github_ownerships (user_id, owner) VALUES (?, ?)");
            foreach ($owners as $owner) {
                $stmtInsert->execute([$userId, $owner]);
            }

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}

