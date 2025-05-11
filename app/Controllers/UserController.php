<?php
namespace App\Controllers;

use App\Services\JWTService;
use App\Services\UserService;

class UserController {
    private UserService $userService;
    private JWTService $jwtService;

    public function __construct() {
        $this->userService = new UserService();
        $this->jwtService = new JWTService();
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

        // Generate JWT token using the service
        $token = $this->jwtService->generateToken([
            'id' => $user['id'],
            'username' => $user['username']
        ]);

        $payload = $this->jwtService->validateToken($token);

        // Return success with token
        echo json_encode([
            'message' => 'Login successful',
            'user_id' => $user['id'],
            'token' => $token,
            'expires' => $payload['expiration']
        ]);
    }

    public function register() {
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents("php://input"), true);
        if (!$input || !isset($input['username'], $input['github_ownership'], $input['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing fields']);
            return;
        }

        $owners = array_map('trim', explode(',', $input['github_ownership']));

        try {
            $this->userService->createUser($input['username'], $owners, $input['password']);
            echo json_encode(['message' => 'User created']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Server error',
                'details' => $e->getMessage()]
            );
        }
    }
}

