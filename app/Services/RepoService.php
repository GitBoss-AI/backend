<?php
namespace App\Services;

use App\Logger\Logger;

class RepoService extends BaseService {

    private $logFile = 'reposervice';

    public function add(int $user_id, string $repo_url) {
        $parsedUrl = $this->parseGithubUrl($repo_url);
        $repoOwner = $parsedUrl['owner'];
        $repoName = $parsedUrl['name'];

        Logger::info($this->logFile, "Attempting to add repo: $repo_url for user_id=$user_id");

        try {
            $this->assertRepoNotAlreadyTracked($repo_url, $user_id);
            $this->assertUserOwnsGithubOwner($user_id, $repoOwner);
        } catch (\Exception $e) {
            Logger::error($this->logFile, "Validation failed: " . $e->getMessage());
            throw $e;
        }

        if (!$this->isPublicRepo($repoOwner, $repoName)) {
            throw new \Exception("The repository $repoOwner/$repoName does not exist or is not public.");
        }

        try {
            $this->db->pdo()->beginTransaction();

            $this->db->insert('repos', [
                'url' => $repo_url,
                'owner' => $repoOwner,
                'name' => $repoName,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $result = $this->db->selectOne("SELECT id FROM repos ORDER BY id DESC LIMIT 1");
            $newRepoId = $result['id'];

            Logger::info($this->logFile, "Inserted repo [$repoName] with ID $newRepoId.");

            $repoStatsData = [
                'repo_id' => $newRepoId,
                'snapshot_date' => date('Y-m-d H:i:s'),
                'commits' => $this->getRepoCommitCount($repoOwner, $repoName),
                'open_prs' => $this->getRepoOpenPrCount($repoOwner, $repoName),
                'merged_prs' => $this->getRepoMergedPrCount($repoOwner, $repoName),
                'open_issues' => $this->getRepoOpenIssueCount($repoOwner, $repoName),
                'reviews' => $this->getRepoReviewCount($repoOwner, $repoName)
            ];

            $this->db->insert('repo_stats', $repoStatsData);
            Logger::info($this->logFile, "Created stats snapshot for repo $newRepoId");

            $this->db->pdo()->commit();

            return $newRepoId;

        } catch (\Exception $e) {
            $this->db->pdo()->rollBack();
            Logger::error($this->logFile, "Failed to add repo: " . $e->getMessage());
            throw $e;
        }
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

    public function getStats(int $repoId, ?string $timeWindow = null) {
        // Ensure repo exists
        $repo = $this->db->selectOne(
            "SELECT id FROM repos WHERE id = :id",
            ['id' => $repoId]
        );

        if (!$repo) {
            throw new \Exception("Repo not found.");
        }

        // Get the latest snapshot
        $latest = $this->db->selectOne(
            "SELECT * FROM repo_stats
             WHERE repo_id = :repo_id
             ORDER BY snapshot_date DESC
             LIMIT 1",
            ['repo_id' => $repoId]
        );

        if (!$latest) {
            throw new \Exception("No snapshot data available.");
        }

        if (!$timeWindow) {
            return [
                'date' => $latest['snapshot_date'],
                'stats' => $this->stripRepoFields($latest)
            ];
        }

        // Calculate time window
        try {
            $startDate = $this->getStartDate($timeWindow);
        } catch (\Exception $e) {
            throw new \Exception("Invalid time_window: " . $e->getMessage());
        }

        $earlier = $this->db->selectOne(
            "SELECT * FROM repo_stats
            WHERE repo_id = :repo_id AND snapshot_date <= :start_date
            ORDER BY snapshot_date DESC
            LIMIT 1",
            ['repo_id' => $repoId, 'start_date' => $startDate]
        );

        if (!$earlier) {
            throw new \Exception("No snapshot found at or before start of time window.");
        }

        // Compute deltas
        return [
            'from' => $earlier['snapshot_date'],
            'to' => $latest['snapshot_date'],
            'stats' => [
                'commits'      => $latest['commits']      - $earlier['commits'],
                'open_prs'     => $latest['open_prs']     - $earlier['open_prs'],
                'merged_prs'   => $latest['merged_prs']   - $earlier['merged_prs'],
                'open_issues'  => $latest['open_issues']  - $earlier['open_issues'],
                'reviews'      => $latest['reviews']      - $earlier['reviews'],
            ]
        ];
    }

    public function getRepoCommitCount(string $owner, string $repo) {
        Logger::info($this->logFile, "Fetching commits for $owner/$repo");
        $commits = $this->githubClient->getPaginated("/repos/$owner/$repo/commits");
        Logger::info($this->logFile, "Commit count for $owner/$repo: " . count($commits));
        return count($commits);
    }

    public function getRepoOpenPrCount(string $owner, string $repo) {
        Logger::info($this->logFile, "Fetching open PR count for $owner/$repo");
        $query = "repo:$owner/$repo type:pr state:open";
        $count = $this->getSearchCount($query);
        Logger::info($this->logFile, "Open PR count: $count");
        return $count;
    }

    public function getRepoMergedPrCount(string $owner, string $repo) {
        Logger::info($this->logFile, "Fetching merged PR count for $owner/$repo");
        $query = "repo:$owner/$repo type:pr is:merged";
        $count = $this->getSearchCount($query);
        Logger::info($this->logFile, "Merged PR count: $count");
        return $count;
    }

    public function getRepoOpenIssueCount(string $owner, string $repo) {
        Logger::info($this->logFile, "Fetching open issue count for $owner/$repo");
        $query = "repo:$owner/$repo type:issue state:open";
        $count = $this->getSearchCount($query);
        Logger::info($this->logFile, "Open issue count: $count");
        return $count;
    }

    public function getRepoReviewCount(string $owner, string $repo) {
        Logger::info($this->logFile, "Fetching review count for $owner/$repo (last 24h)");
        $since = strtotime('-1 day');
        $totalReviews = 0;
        $maxPages = 3;  // Set max pages to get data of last 24 hours

        for ($page = 1; $page <= $maxPages; $page++) {
            $pulls = $this->githubClient->get("repos/$owner/$repo/pulls", [
                'state' => 'open',
                'sort' => 'updated',
                'direction' => 'desc',
                'per_page' => 100,
                'page' => $page
            ]);

            if (empty($pulls)) break;

            foreach ($pulls as $pr) {
                if (!isset($pr['number']) || !isset($pr['updated_at'])) continue;
                if (strtotime($pr['updated_at']) < $since) break 2;

                $reviews = $this->githubClient->get("repos/$owner/$repo/pulls/{$pr['number']}/reviews");
                $totalReviews += count($reviews);
            }
        }

        Logger::info($this->logFile, "Total reviews in last 24h for $owner/$repo: $totalReviews");
        return $totalReviews;
    }

    private function assertRepoNotAlreadyTracked(string $repo_url, int $user_id): void {
        $repo = $this->db->selectOne(
            "SELECT * FROM repos WHERE url = :url",
            ['url' => $repo_url]
        );

        if ($repo) {
            Logger::info($this->logFile, "Repo $repo_url already exists.");

            $ownership = $this->db->selectOne(
                "SELECT * FROM github_ownerships WHERE owner = :owner AND user_id = :user_id",
                ['owner' => $repo['owner'], 'user_id' => $user_id]
            );

            if ($ownership) {
                Logger::error($this->logFile, "User already owns this repo.");
                throw new \Exception("This repository has already been added to the system.");
            } else {
                Logger::error($this->logFile, "Repo is owned by another user.");
                throw new \Exception("This repository is already tracked by another user.");
            }
        }
    }

    private function assertUserOwnsGithubOwner(int $user_id, string $owner): void {
        $ownership = $this->db->selectOne(
            "SELECT * FROM github_ownerships WHERE owner = :owner AND user_id = :user_id",
            ['owner' => $owner, 'user_id' => $user_id]
        );

        if (!$ownership) {
            Logger::error($this->logFile, "User $user_id is not authorized for owner $owner.");
            throw new \Exception("You do not have permission to add repos for owner '$owner'.");
        }
    }

    private function isPublicRepo(string $owner, string $repo): bool {
        try {
            $this->githubClient->get("repos/$owner/$repo");
            return true;
        } catch (\Exception $e) {
            Logger::error($this->logFile, "Repo $owner/$repo is not accessible: " . $e->getMessage());
            return false;
        }
    }
}
