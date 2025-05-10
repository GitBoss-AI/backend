<?php

use App\Controllers\UserController;
use App\Controllers\HealthController;

// Simple dispatcher
$routes = [
    'POST' => [
        '/api-dev/login'    => [UserController::class, 'login'],
        '/api-dev/register' => [UserController::class, 'register'],
    ],
    'GET' => [
        '/api-dev/health'   => [HealthController::class, 'check'],
    ],
];

// Get current route
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip trailing slashes for consistency
$uri = rtrim($uri, '/');

// Match route
if (isset($routes[$method][$uri])) {
    [$class, $methodName] = $routes[$method][$uri];
    (new $class)->$methodName();
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Wait what??']);
}

