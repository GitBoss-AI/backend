<?php
namespace App\Services;

use App\Logger\Logger;

class ContributorService extends BaseService {

    private $logFile = 'contributorservice';

    public function add(int $repo_id, string $repo_url) {
        $parsedUrl = $this->parseGithubUrl($repo_url);
        $repoOwner = $parsedUrl['owner'];
        $repoName = $parsedUrl['name'];

        Logger::info($this->logFile, "Adding contributors for $repoOwner/$repoName (repo_id=$repo_id)");

        $contributors = $this->getContributors($repoOwner, $repoName);

        foreach ($contributors as $contributor) {
            $username = $contributor['login'];
            Logger::info($this->logFile, "Processing contributor: $username");

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
                Logger::info($this->logFile, "Inserted new contributor: $username (id=$contributorId)");
            } else {
                $contributorId = $existingContributor['id'];
                Logger::info($this->logFile, "Contributor already exists: $username (id=$contributorId)");
            }

            // Link contributor to repo
            $this->db->insert('repo_has_contributor', [
                'repo_id' => $repo_id,
                'contributor_id' => $contributorId,
                'first_seen' => date('Y-m-d H:i:s'),
                'last_seen' => date('Y-m-d H:i:s')
            ]);

            Logger::info($this->logFile, "Linked $username to repo_id=$repo_id");

            $contributorStatsData = [
                'contributor_id' => $contributorId,
                'repo_id' => $repo_id,
                'snapshot_date' => date('Y-m-d H:i:s'),
                'commits' => $this->getContributorCommitCount($username, $repoOwner, $repoName),
                'prs_opened' => $this->getContributorPrCount($username, $repoOwner, $repoName),
                'reviews' => $this->getContributorReviewCount($username, $repoOwner, $repoName)
            ];
            $this->db->insert('contributor_stats', $contributorStatsData);

            Logger::info($this->logFile, "Inserted snapshot for $username");
        }
    }

    public function getStats(
        string $githubUsername,
        ?string $timeWindow = null,
        ?int $repoId = null,
        ?int $userId = null
    ) {
        Logger::info($this->logFile, "Fetching stats for $githubUsername (repo_id=$repoId, user_id=$userId, window=$timeWindow)");

        $contributor = $this->db->selectOne(
            "SELECT id FROM contributors WHERE github_username = :username",
            ['username' => $githubUsername]
        );

        if (!$contributor) {
            Logger::error($this->logFile, "Contributor $githubUsername not found");
            throw new \Exception("Contributor not found");
        }

        $contributorId = $contributor['id'];

        if ($repoId !== null && $userId !== null) {
            $ownership = $this->db->selectOne(
                "SELECT r.id FROM repos r
             JOIN github_ownerships go ON r.owner = go.owner
             WHERE r.id = :repo_id AND go.user_id = :user_id",
                ['repo_id' => $repoId, 'user_id' => $userId]
            );

            if (!$ownership) {
                Logger::error($this->logFile, "User $userId does not own repo $repoId");
                throw new \Exception("User does not own the requested repository.");
            }
        }

        $conditions = "contributor_id = :contributor_id";
        $params = ['contributor_id' => $contributorId];

        if ($repoId !== null) {
            $conditions .= " AND repo_id = :repo_id";
            $params['repo_id'] = $repoId;
        }

        $latest = $this->db->selectOne(
            "SELECT * FROM contributor_stats
             WHERE $conditions
             ORDER BY snapshot_date DESC
             LIMIT 1",
            $params
        );

        if (!$latest) {
            Logger::error($this->logFile, "No snapshot found for $githubUsername");
            throw new \Exception("No snapshot data available.");
        }

        if (!$timeWindow) {
            Logger::info($this->logFile, "Returning latest snapshot for $githubUsername");
            return [
                'date' => $latest['snapshot_date'],
                'stats' => $this->stripRepoFields($latest)
            ];
        }

        try {
            $startDate = $this->getStartDate($timeWindow);
        } catch (\Exception $e) {
            Logger::error($this->logFile, "Invalid time window '$timeWindow' for $githubUsername");
            throw new \Exception("Invalid time_window: " . $e->getMessage());
        }

        $earlier = $this->db->selectOne(
            "SELECT * FROM contributor_stats
            WHERE $conditions AND snapshot_date <= :start_date
            ORDER BY snapshot_date DESC
            LIMIT 1",
            array_merge($params, ['start_date' => $startDate])
        );

        if (!$earlier) {
            Logger::error($this->logFile, "No earlier snapshot for $githubUsername before $startDate");
            throw new \Exception("No snapshot found at or before start of time window.");
        }

        Logger::info($this->logFile, "Computed delta stats for $githubUsername");
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

    public function topPerformers(int $repoId, ?string $timeWindow = null) {
        Logger::info($this->logFile, "Calculating top performers for repo_id=$repoId, window=$timeWindow");

        $startDate = null;

        if ($timeWindow) {
            try {
                $startDate = $this->getStartDate($timeWindow);
            } catch (\Exception $e) {
                Logger::error($this->logFile, "Invalid time window '$timeWindow'");
                throw new \Exception("Invalid time_window: " . $e->getMessage());
            }
        }

        $contributors = $this->db->selectAll("
            SELECT c.id, c.github_username
            FROM contributors c
            JOIN repo_has_contributor rhc ON rhc.contributor_id = c.id
            WHERE rhc.repo_id = :repo_id",
            ['repo_id' => $repoId]
        );

        Logger::info($this->logFile, "Found " . count($contributors) . " contributors");

        $allStats = [];

        foreach ($contributors as $contributor) {
            $cid = $contributor['id'];
            $username = $contributor['github_username'];

            $latest = $this->db->selectOne(
                "SELECT * FROM contributor_stats
             WHERE contributor_id = :cid AND repo_id = :rid
             ORDER BY snapshot_date DESC
             LIMIT 1",
                ['cid' => $cid, 'rid' => $repoId]
            );

            if (!$latest) continue;

            if ($startDate) {
                $earlier = $this->db->selectOne(
                    "SELECT * FROM contributor_stats
                     WHERE contributor_id = :cid AND repo_id = :rid AND snapshot_date <= :start
                     ORDER BY snapshot_date DESC
                     LIMIT 1",
                    ['cid' => $cid, 'rid' => $repoId, 'start' => $startDate]
                );

                if (!$earlier) continue;

                $stats = [
                    'github_username' => $username,
                    'commits' => $latest['commits'] - $earlier['commits'],
                    'prs_opened' => $latest['prs_opened'] - $earlier['prs_opened'],
                    'reviews' => $latest['reviews'] - $earlier['reviews'],
                ];
            } else {
                $stats = [
                    'github_username' => $username,
                    'commits' => $latest['commits'],
                    'prs_opened' => $latest['prs_opened'],
                    'reviews' => $latest['reviews'],
                ];
            }

            $allStats[] = $stats;
        }

        Logger::info($this->logFile, "Collected stats for " . count($allStats) . " contributors");

        $getTop = function($metric) use ($allStats) {
            $sorted = $allStats;
            usort($sorted, fn($a, $b) => $b[$metric] <=> $a[$metric]);
            return array_slice($sorted, 0, 10);
        };

        return [
            'top_committers' => $getTop('commits'),
            'top_prs'        => $getTop('prs_opened'),
            'top_reviewers'  => $getTop('reviews'),
        ];
    }

    public function getRecentEvents(int $repoId) {
        Logger::info($this->logFile, "Fetching recent events for repo_id=$repoId");

        $events = $this->db->selectAll(
            "SELECT cae.*, c.github_username
             FROM contributor_activity_events cae
             JOIN contributors c ON cae.contributor_id = c.id
             WHERE cae.repo_id = :repo_id
             AND cae.occurred_at >= CURRENT_DATE
             ORDER BY cae.quantity DESC, cae.occurred_at DESC
             LIMIT 10",
            ['repo_id' => $repoId]
        );

        Logger::info($this->logFile, "Fetched " . count($events) . " events");

        foreach ($events as $i => &$event) {
            $event['highlight'] = $i < 3;
        }

        return $events;
    }

    public function getContributors(string $owner, string $repo) {
        return $this->githubClient->getPaginated("repos/$owner/$repo/contributors");
    }

    public function getContributorCommitCount(string $username, string $owner, string $repo, string $since) {
        $commits = $this->githubClient->getPaginated("repos/$owner/$repo/commits", [
            'author' => $username,
            'since' => $since
        ]);
        return count($commits);
    }

    public function getContributorPrCount(string $username, string $owner, string $repo) {
        $since = date('Y-m-d', strtotime('-1 day'));
        $query = "repo:$owner/$repo type:pr author:$username created:>=$since";

        $result = $this->githubClient->get("search/issues", ['q' => $query]);
        return $result['total_count'];
    }

    public function getContributorReviewCount(string $username, string $owner, string $repo, string $since) {
        $totalReviews = 0;
        $sinceTimestamp = strtotime($since);

        $pulls = $this->githubClient->getPaginated("repos/$owner/$repo/pulls", [
            'state' => 'open'
        ]);

        foreach ($pulls as $pr) {
            $prNumber = $pr['number'] ?? null;
            if (!$prNumber) continue;

            $reviews = $this->githubClient->getPaginated("repos/$owner/$repo/pulls/$prNumber/reviews");

            foreach ($reviews as $review) {
                if (
                    isset($review['user']['login'], $review['submitted_at']) &&
                    $review['user']['login'] === $username &&
                    strtotime($review['submitted_at']) >= $sinceTimestamp
                ) {
                    $totalReviews++;
                }
            }
        }

        return $totalReviews;
    }
}
