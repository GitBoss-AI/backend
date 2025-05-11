<?php
namespace App\Controllers;

use App\Services\ContributorService;

class ContributorController
{
    private $contributorService;

    public function __construct() {
        $this->contributorService = new ContributorService();
    }

    public function getContributorStats() {
        header('Content-Type: application/json');

        $githubUsername = $_GET["github_username"] ?? null;
        $timeWindow = $_GET["time_window"] ?? null;
        $repoId = (int) $_GET["repo_id"] ?? null;
        $userId = (int) $_GET["user_id"] ?? null;

        if (!$githubUsername) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing github_username']);
            return;
        }

        try {
            $contributorStats = $this->contributorService->getStats(
                $githubUsername,
                $timeWindow,
                $repoId,
                $userId
            );
            http_response_code(200);
            echo json_encode([
                'message' => 'Contributor stats successfully retrieved',
                'contributorStats' => $contributorStats
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
