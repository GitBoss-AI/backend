<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

# Autoload classes
require_once __DIR__ . '/../vendor/autoload.php';

# Load environment variables
$dotenvPath = __DIR__ . '/../.env';
if (file_exists($dotenvPath)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(dirname($dotenvPath));
    $dotenv->load();
}

# Apply CORS
\App\Middleware\CORS::handle();

# Include routes
require_once __DIR__ . '/../routes/api.php';
