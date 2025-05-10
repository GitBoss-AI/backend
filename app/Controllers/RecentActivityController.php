<?php
namespace App\Controllers;
use App\Services\GitHubService;

class RecentActivityController
{
    public function getRecentActivity()
    {
        try {
            $owner = $_GET['owner'] ?? $_ENV['GITHUB_OWNER'];
            $repo = $_GET['repo'] ?? $_ENV['GITHUB_REPO'];
            $limit = (int)($_GET['limit'] ?? 10);

            if (!$owner || !$repo) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Owner and repo parameters are required']);
                return;
            }

            $githubService = new GitHubService();
            $activities = $githubService->getRecentActivity($owner, $repo, $limit);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $activities]);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}