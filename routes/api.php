<?php
use App\Controllers\HealthController;
use App\Controllers\UserController;
use App\Controllers\RepoController;
use App\Controllers\ContributorController;
use App\Controllers\RecentActivityController;

// Simple dispatcher
$routes = [
    'POST' => [
        // User
        '/api-dev/login'    => [UserController::class, 'login'],
        '/api-dev/register' => [UserController::class, 'register'],

        // Repo
        '/api-dev/repo/add' => [RepoController::class, 'addRepo'],
    ],
    'GET' => [
        // Health
        '/api-dev/health'   => [HealthController::class, 'check'],

        // Repo
        '/api-dev/repo/getAll' => [RepoController::class, 'getAllRepos'],
        '/api-dev/repo/stats' => [RepoController::class, 'getRepoStats'],

        // Contributor
        '/api-dev/contributor/stats' => [ContributorController::class, 'getContributorStats'],
        '/api-dev/contributor/topPerformers' => [ContributorController::class, 'getTopPerformers'],
        '/api-dev/contributor/recent-activity' => [ContributorController::class, 'getRecentActivity'],

        // Team
        '/api-dev/team/timeline' => [TeamController::class, 'getTimeline'],

        // Activity feed
        //'/api-dev/recent-activity' => [RecentActivityController::class, 'getRecentActivity'],
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
    return json_encode(['error' => 'Not found']);
}
