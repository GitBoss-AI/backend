<?php
namespace App\Services;
use App\Database\DB;
use GuzzleHttp\Client;
use PDO;

class GitHubService
{
    public function parseGithubUrl(string $url) {
        if (preg_match('#github\.com/([^/]+)/([^/]+)#', $url, $matches)) {
            return [
                'owner' => $matches[1],
                'name'  => $matches[2]
            ];
        }
        return null;
    }
}
