<?php
namespace App\Services;

use App\Database\DB;

class BaseService
{
    protected $db;
    protected $githubClient;

    public function __construct() {
        $this->db = DB::getInstance();
        $this->githubClient = new GithubClient();
    }

    protected function stripRepoFields(array $row) {
        unset($row['id'], $row['repo_id'], $row['snapshot_date'], $row['contributor_id']);
        return $row;
    }

    protected function parseGithubUrl(string $url) {
        if (preg_match('#github\.com/([^/]+)/([^/]+)#', $url, $matches)) {
            return [
                'owner' => $matches[1],
                'name'  => $matches[2]
            ];
        }
        return null;
    }

    protected function getStartDate(string $timeWindow): string {
        $patterns = [
            '/^(\d+)d$/' => fn($m) => "{$m[1]} days",
            '/^(\d+)w$/' => fn($m) => (7 * (int)$m[1]) . " days",
            '/^(\d+)m$/' => fn($m) => "{$m[1]} months",
        ];

        foreach ($patterns as $pattern => $handler) {
            if (preg_match($pattern, $timeWindow, $m)) {
                $interval = $handler($m);
                return (new DateTime())->modify("-$interval")->format('Y-m-d');
            }
        }
        throw new \Exception("Invalid time_window format. Use Nd, Nw, or Nm.");
    }

    protected function getSearchCount(string $q) {
        $result = $this->githubClient->get("search/issues", ['q' => $q]);
        return $result['total_count'];
    }
}
