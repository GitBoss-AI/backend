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

    public function createUser(string $username, array $owners, string $password) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Insert user
            $userData = [
                'username' => $username,
                'password' => $passwordHash,
            ];
            $this->db->insert('users', $userData);

            // Check for already claimed owners
            $placeholders = implode(',', array_fill(0, count($owners), '?'));
            $existingOwners = $this->db->selectAll(
                "SELECT owner FROM github_ownerships WHERE owner IN ($placeholders)",
                $owners
            );

            if (!empty($existingOwners)) {
                throw new \Exception("These GitHub owners are already claimed: " . implode(', ', $existingOwners));
            }

            // Insert ownerships
            $userId = $this->db->selectOne(
                "SELECT id FROM users WHERE username = :username LIMIT 1",
                ['username' => $username]
            );

            foreach ($owners as $owner) {
                $this->db->insert('github_ownerships', [
                    'user_id' => $userId,
                    'owner' => $owner,
                ]);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}

