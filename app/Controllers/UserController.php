<?php
namespace App\Controllers;

use App\Services\UserService;

class UserController {
    private UserService $userService;

    public function __construct() {
        $this->userService = new UserService();
    }

    public function login() {
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents("php://input"), true);
        if (!$input || !isset($input['username'], $input['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing credentials']);
            return;
        }

        $user = $this->userService->findUserByUsername($input['username']);
        if (!$user || !$this->userService->verifyPassword($input['password'], $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid username or password']);
            return;
        }

        echo json_encode(['message' => 'Login successful', 'user_id' => $user['id']]);
    }

    public function register() {
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents("php://input"), true);
        if (!$input || !isset($input['username'], $input['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing fields']);
            return;
        }

        try {
            $this->userService->createUser($input['username'], $input['password']);
            echo json_encode(['message' => 'User created']);
        } catch (\PDOException $e) {
            http_response_code(409);
            echo json_encode(['error' => 'Username already exists']);
        }
    }
}

