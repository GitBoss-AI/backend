<?php

namespace App\Middleware;

class CORS {
    /**
     * Handle CORS headers for all responses
     */
    public static function handle() {
        header('Access-Control-Allow-Origin: *');
        
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Max-Age: 3600');
            http_response_code(204);
            exit(0);
        }
    }
}
