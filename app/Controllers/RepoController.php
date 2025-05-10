<?php
namespace App\Controllers;

use App\Services\RepoService;

class RepoController {
    private $repoService;

    public function __construct() {
        $this->repoService = new RepoService();
    }
    public function addRepo() {
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents("php://input"), true);
        if (!$input || !isset($input['repo_url'], $input['user_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing fields']);
        }

        $repo_url = $input['repo_url'];
        $user_id = (int) $input['user_id'];

        try {
            $this->repoService->add($user_id, $repo_url);
            http_response_code(200);
            echo json_encode(['message' => 'Repository successfully added.']);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getAllRepos() {
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents("php://input"), true);
        if (!$input || !isset($input['user_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing fields']);
        }

        $user_id = $input['user_id'];

        try {
            $this->repoService->getAll($user_id);
            http_response_code(200);
            echo json_encode(['message' => 'Repos successfully retrieved.']);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}