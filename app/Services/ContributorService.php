<?php
namespace App\Services;

class ContributorService extends BaseService {
    public function add(int $repo_id, string $repo_url) {
        $parsedUrl = $this->parseGithubUrl($repo_url);
        $repoOwner = $parsedUrl['owner'];
        $repoName = $parsedUrl['name'];

        $contributors = $this->getContributors($repoOwner, $repoName);
        foreach ($contributors as $contributor) {
            $username = $contributor['login'];

            // Check if contributor exists
            $existingContributor = $this->db->selectOne(
                "SELECT id FROM contributors WHERE github_username = :username",
                ['username' => $username]
            );

            if (!$existingContributor) {
                $this->db->insert('contributors', [
                    'github_username' => $username,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $contributorId = $this->db->pdo()->lastInsertId();
            } else {
                $contributorId = $contributor['id'];
            }

            // Link contributor to repo
            $this->db->insert('repo_has_contributor', [
                'repo_id' => $repo_id,
                'contributor_id' => $contributorId,
                'first_seen' => date('Y-m-d'),
                'last_seen' => date('Y-m-d')
            ]);

            // Fetch stats from github api and create stats snapshot
            $contributorStatsData = [
                'contributor_id' => $contributorId,
                'repo_id' => $repo_id,
                'snapshot_date' => date('Y-m-d H:i:s'),
                'commits' => $this->getContributorCommitCount($username, $repoOwner, $repoName),
                'prs_opened' => $this->getContributorPrCount($username, $repoOwner, $repoName),
                'reviews' => $this->getContributorReviewCount($username, $repoOwner, $repoName)
            ];
            $this->db->insert('repo_stats', $contributorStatsData);
        }
    }

    public function getStats(string $githubUsername, string $timeWindow) {
        $contributor = $this->db->selectOne(
            "SELECT id FROM contributors WHERE github_username = :username",
            ['username' => $githubUsername]
        );

        if (!$contributor) {
            throw new \Exception("Contributor not found");
        }

        $contributorId = $contributor['id'];
        $latest = $this->db->selectOne(
            "SELECT * FROM contributor_stats
             WHERE contributor_id = :contributor_id
             ORDER BY snapshot_date DESC
             LIMIT 1",
            ['contributor_id' => $contributorId]
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
            "SELECT * FROM contributor_stats
            WHERE contributor_id = :contributor_id AND snapshot_date <= :start_date
            ORDER BY snapshot_date DESC
            LIMIT 1",
            ['contributor_id' => $contributorId, 'start_date' => $startDate]
        );

        if (!$earlier) {
            throw new \Exception("No snapshot found at or before start of time window.");
        }

        // Compute deltas
        return [
            'from' => $earlier['snapshot_date'],
            'to' => $latest['snapshot_date'],
            'stats' => [
                'commits'       => $latest['commits']       - $earlier['commits'],
                'prs_opened'    => $latest['prs_opened']    - $earlier['prs_opened'],
                'reviews'       => $latest['reviews']       - $earlier['reviews'],
            ]
        ];
    }

    public function getContributors(string $owner, string $repo) {
        return $this->githubClient->getPaginated("repos/$owner/$repo/contributors");
    }

    public function getContributorCommitCount(string $username, string $owner, string $repo) {
        $commits = $this->githubClient->getPaginated("repos/$owner/$repo/commits", [
            'author' => $username,
        ]);
        return count($commits);
    }

    public function getContributorPrCount(string $username, string $owner, string $repo) {
        $query = "repo:$owner/$repo type:pr author:$username";

        $result = $this->githubClient->get("search/issues", ['q' => $query]);
        return $result['total_count'];
    }

    public function getContributorReviewCount(string $username, string $owner, string $repo) {
        $totalReviews = 0;

        $pulls = $this->githubClient->getPaginated("repos/$owner/$repo/pulls", [
            'state' => 'all'
        ]);

        foreach ($pulls as $pr) {
            $prNumber = $pr['number'] ?? null;
            if (!$prNumber) continue;

            $reviews = $this->githubClient->get("repos/$owner/$repo/pulls/$prNumber/reviews");

            foreach ($reviews as $review) {
                if (($review['user']['login'] ?? '') === $username) {
                    $totalReviews++;
                }
            }
        }
        return $totalReviews;
    }
}
