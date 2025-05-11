<?php
namespace App\Services;

class RepoService extends BaseService {
    public function add(int $user_id, string $repo_url) {
        $repo = $this->db->selectOne(
            "SELECT * FROM repos WHERE url = :url",
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

        // Insert repo to database
        $parsedUrl = $this->parseGithubUrl($repo_url);
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

        $result = $this->db->selectOne("SELECT id FROM repos ORDER BY id DESC LIMIT 1");
        $newRepoId = $result['id'];

        // Fetch stats from github api and create stats snapshot
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

        return $newRepoId;
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

    public function getStats(string $repo_url, ?string $timeWindow = null) {
        $repo = $this->db->selectOne(
            "SELECT id FROM repos WHERE url = :url",
            ['url' => $repo_url]
        );

        if (!$repo) {
            throw new \Exception("Repo not found.");
        }

        $repoId = $repo['id'];
        $latest = $this->db->selectOne(
            "SELECT * FROM repo_stats
             WHERE repo_id = :repo_id
             ORDER BY snapshot_date DESC
             LIMIT 1",
            ['repo_id' => $repoId]
        );

        if (!$latest) {
            // TODO: get stats from github api
            throw new \Exception("No snapshot data available.");
        }

        // If no timeWindow is given just return the latest stats
        if (!$timeWindow) {
            return [
                'date' => $latest['snapshot_date'],
                'stats' => $this->stripRepoFields($latest)
            ];
        }

        // Compute the start date threshold
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
                'commits'    => $latest['commits']    - $earlier['commits'],
                'open_prs'   => $latest['open_prs']   - $earlier['open_prs'],
                'merged_prs' => $latest['merged_prs'] - $earlier['merged_prs'],
                'open_issues'     => $latest['open_issues']     - $earlier['open_issues'],
                'reviews'    => $latest['reviews']    - $earlier['reviews'],
            ]
        ];
    }

    public function getRepoCommitCount(string $owner, string $repo) {
        $commits = $this->githubClient->getPaginated("/repos/$owner/$repo/commits");
        return count($commits);
    }

    public function getRepoOpenPrCount(string $owner, string $repo) {
        $query = "repo:$owner/$repo type:pr state:open";
        return $this->getSearchCount($query);
    }

    public function getRepoMergedPrCount(string $owner, string $repo) {
        $query = "repo:$owner/$repo type:pr is:merged";
        return $this->getSearchCount($query);
    }

    public function getRepoOpenIssueCount(string $owner, string $repo) {
        $query = "repo:$owner/$repo type:issue state:open";
        return $this->getSearchCount($query);
    }

    public function getRepoReviewCount(string $owner, string $repo) {
        $since = strtotime('-1 day');
        $totalReviews = 0;
        $page = 1;

        while (true) {
            $pulls = $this->githubClient->get("repos/$owner/$repo/pulls", [
                'state' => 'all',
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

                if (is_array($reviews)) {
                    $totalReviews += count($reviews);
                }
            }
            $page++;
        }
        return $totalReviews;
    }
}
