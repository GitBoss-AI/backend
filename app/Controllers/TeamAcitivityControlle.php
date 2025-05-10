<?php
namespace App\Controllers;
use App\Services\GitHubService;

class TeamActivityController
{
    public function getTimeline()
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
            $timeline = $githubService->getActivityTimeline($owner, $repo);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $timeline]);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getComparison()
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
            $comparison = $githubService->getDeveloperComparison($owner, $repo);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $comparison]);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}