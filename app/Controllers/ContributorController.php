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
        $repoId = isset($_GET["repo_id"]) ? (int) $_GET["repo_id"] : null;
        $userId = isset($_GET["user_id"]) ? (int) $_GET["user_id"] : null;

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

    public function getTopPerformers() {
        header('Content-Type: application/json');

        $timeWindow = $_GET["time_window"] ?? null;
        $repoId = isset($_GET["repo_id"]) ? (int) $_GET["repo_id"] : null;

        if (!$repoId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing repo_id']);
            return;
        }

        try {
            $data = $this->contributorService->topPerformers($repoId, $timeWindow);
            http_response_code(200);
            echo json_encode($data);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getRecentActivity() {
        header('Content-Type: application/json');

        $repoId = $_GET['repo_id'] ?? null;

        if (!$repoId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing repo_id']);
            return;
        }

        try {
            $data = $this->contributorService->getRecentEvents((int) $repoId);
            echo json_encode([
                'message' => 'Recent activity retrieved successfully.',
                'events' => $data
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
