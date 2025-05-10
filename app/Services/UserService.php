<?php
namespace App\Services;

use App\Database\DB;
use PDO;

class UserService {
	
    private $db;
    
    public __construct() {
	$this->db = DB::getInstance();
    }

    public function findUserByUsername(string $username): ?array {
    	return $this->db->selectOne(
	    "SELECT * FROM users WHERE username = :username LIMIT 1",
	    ['username' => username]
	);
    }

    public function createUser(string $username, string $password): bool {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
    	return $this->db->insert('users', [
            'username' => $username,
            'password_hash' => $passwordHash
        ]);	
    }

    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}

