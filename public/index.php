<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

# Load classes
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../routes/api.php';
} catch (Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

# Load environment variables
$dotenvPath = __DIR__ . '/../.env';
if (file_exists($dotenvPath)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(dirname($dotenvPath));
    $dotenv->load();
}

# Apply CORS
\App\Middleware\CORS::handle();
