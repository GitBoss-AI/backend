<?php
namespace App\Controllers;
use App\Services\GitHubService;

class TeamActivityController
{
    public function getTimeline()
    {
        try {
            $owner = $_GET['owner'] ?? $_ENV['GITHUB_OWNER'];
            $repo = $_GET['repo'] ?? $_ENV['GITHUB_REPO'];

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
            $owner = $_GET['owner'] ?? $_ENV['GITHUB_OWNER'];
            $repo = $_GET['repo'] ?? $_ENV['GITHUB_OWNER'];

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