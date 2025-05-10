<?php
namespace App\Controllers;
use App\Services\GitHubService;

class RepositoryStatsController
{
    public function getStats()
    {
        try {
            $config = require __DIR__ . '/../config/config.php';
            
            $owner = $_GET['owner'] ?? $config['github']['default_owner'];
            $repo = $_GET['repo'] ?? $config['github']['default_repo'];

            if (!$owner || !$repo) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Owner and repo parameters are required']);
                return;
            }

            $githubService = new GitHubService();
            $stats = $githubService->getRepositoryStats($owner, $repo);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}