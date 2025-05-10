<?php
namespace App\Services;

use App\Database\DB;
use PDO;

class RepoService {
    private $db;
    private $githubService;

    public function __construct() {
        $this->db = DB::getInstance();
        $this->githubService = new GithubService();
    }

    public function add(int $user_id, string $repo_url) {
        $repo = $this->db->selectOne(
            "SELECT id FROM repos WHERE url = :url",
            ['url' => $repo_url]
        );

        if ($repo) {
            // Check if the existing repo is owned by the same user
            $ownership = $this->db->selectOne(
                "SELECT * FROM github_ownerships WHERE owner = :owner AND user_id = :user_id",
                ['owner' => $repo['owner'], 'user_id' => $user_id]
            );

            if ($ownership) {
                throw new \Exception("This repository has already been added to the system.");
            } else {
                throw new \Exception("This repository is already tracked by another user.");
            }
        }

        $parsedUrl = $this->githubService->parseGithubUrl($repo_url);
        $repoOwner = $parsedUrl['owner'];
        $repoName = $parsedUrl['name'];

        $ownership = $this->db->selectOne(
            "SELECT * FROM github_ownerships WHERE owner = :owner AND user_id = :user_id",
            ['owner' => $repoOwner, 'user_id' => $user_id]
        );
        if (!$ownership) {
            throw new \Exception("You do not have permission to add repos for owner '$repoOwner'.");
        }

        $repoData = [
            'url' => $repo_url,
            'owner' => $repoOwner,
            'name' => $repoName,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $this->db->insert('repos', $repoData);
    }

    public function getAll(int $user_id) {
        return $this->db->selectAll(
            "SELECT r.*
            FROM repos r
            JOIN github_ownerships go ON r.owner = go.owner
            WHERE go.user_id = :user_id",
            ['user_id' => $user_id]
        );
    }
}