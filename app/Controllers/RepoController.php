<?php
namespace App\Controllers;

use App\Services\ContributorService;
use App\Services\RepoService;

class RepoController {
    private $repoService;
    private $contributorService;

    public function __construct() {
        $this->repoService = new RepoService();
        $this->contributorService = new ContributorService();
    }

    public function addRepo() {
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents("php://input"), true);
        if (!$input || !isset($input['repo_url'], $input['user_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing fields']);
            return;
        }

        $repo_url = $input['repo_url'];
        $user_id = (int) $input['user_id'];

        try {
            // Add repo & contributors
            $repo_id = $this->repoService->add($user_id, $repo_url);
            $this->contributorService->add($repo_id, $repo_url);
            http_response_code(200);
            echo json_encode(['message' => 'Repository successfully added.']);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getAllRepos() {
        header('Content-Type: application/json');

        $user_id = $_GET['user_id'] ?? null;
        if (!$user_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing user_id']);
            return;
        }

        try {
            $allRepos = $this->repoService->getAll($user_id);
            http_response_code(200);
            echo json_encode([
                'message' => 'Repos successfully retrieved.',
                'repos' => $allRepos
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getRepoStats() {
        header('Content-Type: application/json');

        $repoUrl = $_GET['repo_url'] ?? null;
        $timeWindow = $_GET['time_window'] ?? null;
        if (!$repoUrl) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing repo_url']);
            return;
        }

        try {
            $repoStats = $this->repoService->getStats($repoUrl, $timeWindow);
            http_response_code(200);
            echo json_encode([
                'message' => 'Repos stats successfully retrieved.',
                'repoStats' => $repoStats
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
