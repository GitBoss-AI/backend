<?php
namespace App\Controllers;

class HealthController {
    public function check() {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
            'server' => 'GitBoss API Development Environment'
        ]);
    } 
}
