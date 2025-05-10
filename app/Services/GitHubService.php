<?php
namespace App\Services;
use App\Database\DB;
use GuzzleHttp\Client;
use PDO;

class GitHubService
{
    private $client;
    private $db;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => $_ENV['GITHUB_API_URL'] ?? 'https://api.github.com',
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'Authorization' => 'Bearer ' . $_ENV['GITHUB_TOKEN'],
                'X-GitHub-Api-Version' => '2022-11-28'
            ]
        ]);
        $this->db = DB::getInstance();
    }

    public function getRepositoryStats($owner, $repo)
    {
        try {
            // Get repo from database or create with flag indicating if it's new
            $repoResult = $this->getOrCreateRepo($owner, $repo);
            $repoData = $repoResult['data'];
            $isNewRepo = $repoResult['is_new'];
            $repoId = $repoData['id'];
            
            // If existing repo, return latest snapshot or force fresh data
            if (!$isNewRepo) {
                $latestSnapshot = $this->db->selectOne(
                    "SELECT * FROM repo_stats_snapshot 
                     WHERE repo_id = :repo_id 
                     ORDER BY snapshot_date DESC 
                     LIMIT 1",
                    ['repo_id' => $repoId]
                );
                
                if ($latestSnapshot) {
                    $lastPeriodChange = $this->calculatePeriodChange($repoId);
                    
                    return [
                        'total_commits' => (int)$latestSnapshot['commits'],
                        'open_prs' => (int)$latestSnapshot['open_prs'],
                        'closed_prs' => (int)$latestSnapshot['closed_prs'],
                        'code_reviews' => (int)$latestSnapshot['reviews'],
                        'active_issues' => (int)$latestSnapshot['issues'],
                        'last_period_change' => $lastPeriodChange
                    ];
                }
            }
            
            // For new repos OR if no snapshot exists, fetch fresh data from GitHub API
            $contributors = $this->paginate("repos/{$owner}/{$repo}/stats/contributors");
            $totalCommits = array_sum(array_column($contributors, 'total'));
            
            $allOpenPRs = $this->paginate("repos/{$owner}/{$repo}/pulls", ['state' => 'open']);
            $allClosedPRs = $this->paginate("repos/{$owner}/{$repo}/pulls", ['state' => 'closed']);
            
            $allIssues = $this->paginate("repos/{$owner}/{$repo}/issues", ['state' => 'open']);
            $activeIssues = array_filter($allIssues, fn($issue) => !isset($issue['pull_request']));
            
            $codeReviews = $this->getCodeReviewsCount($owner, $repo);
            
            // Store snapshot in database
            $today = date('Y-m-d');
            $snapshot = [
                'repo_id' => $repoId,
                'snapshot_date' => $today,
                'commits' => $totalCommits,
                'open_prs' => count($allOpenPRs),
                'closed_prs' => count($allClosedPRs),
                'issues' => count($activeIssues),
                'reviews' => $codeReviews
            ];
            
            $this->db->insert('repo_stats_snapshot', $snapshot);
            
            // Removed: Sync contributors - will be handled by workers
            
            $lastPeriodChange = $this->calculatePeriodChange($repoId);
            
            // Ensure integers are returned as integers
            return [
                'total_commits' => $totalCommits,
                'open_prs' => count($allOpenPRs),
                'closed_prs' => count($allClosedPRs),
                'code_reviews' => $codeReviews,
                'active_issues' => count($activeIssues),
                'last_period_change' => $lastPeriodChange
            ];
            
        } catch (\Exception $e) {
            // Fall back to API-only approach on database errors
            $contributors = $this->paginate("repos/{$owner}/{$repo}/stats/contributors");
            $totalCommits = array_sum(array_column($contributors, 'total'));
            
            $allOpenPRs = $this->paginate("repos/{$owner}/{$repo}/pulls", ['state' => 'open']);
            $allClosedPRs = $this->paginate("repos/{$owner}/{$repo}/pulls", ['state' => 'closed']);
            
            $allIssues = $this->paginate("repos/{$owner}/{$repo}/issues", ['state' => 'open']);
            $activeIssues = array_filter($allIssues, fn($issue) => !isset($issue['pull_request']));
            
            $codeReviews = $this->getCodeReviewsCount($owner, $repo);
            $lastPeriodChange = $this->calculatePeriodChangeFromAPI($owner, $repo);
            
            return [
                'total_commits' => $totalCommits,
                'open_prs' => count($allOpenPRs),
                'closed_prs' => count($allClosedPRs),
                'code_reviews' => $codeReviews,
                'active_issues' => count($activeIssues),
                'last_period_change' => $lastPeriodChange
            ];
        }
    }

    public function getActivityTimeline($owner, $repo)
    {
        try {
            $repoResult = $this->getOrCreateRepo($owner, $repo);
            $repoData = $repoResult['data'];
            $repoId = $repoData['id'];
        } catch (\Exception $e) {
            // Continue without database if there's an error
        }
        
        // Fetch data from GitHub API
        $weeklyActivity = $this->makeRequest("repos/{$owner}/{$repo}/stats/commit_activity");
        
        $timeline = [];
        $lastFourWeeks = array_slice($weeklyActivity, -4, 4);
        
        foreach ($lastFourWeeks as $index => $week) {
            $weekNumber = $index + 1;
            
            $timeline[] = [
                'week' => 'W' . $weekNumber,
                'commits' => (int)$week['total'],
                'prs' => $this->countInDateRange("repos/{$owner}/{$repo}/pulls", 
                    $week['week'], $week['week'] + 604800),
                'reviews' => $this->getReviewCountInWeek($owner, $repo, $week['week'])
            ];
        }
        
        return $timeline;
    }

    public function getDeveloperComparison($owner, $repo)
    {
        try {
            $repoResult = $this->getOrCreateRepo($owner, $repo);
            $repoData = $repoResult['data'];
            $repoId = $repoData['id'];
            
            // Get all contributors for this repo
            $contributors = $this->db->selectAll(
                "SELECT c.* FROM contributors c
                 JOIN repo_has_contributor rhc ON c.id = rhc.contributor_id
                 WHERE rhc.repo_id = :repo_id
                 ORDER BY c.created_at",
                ['repo_id' => $repoId]
            );
            
            $developers = [];
            $today = date('Y-m-d');
            
            foreach ($contributors as $contributor) {
                $stats = $this->db->selectOne(
                    "SELECT * FROM contributor_stats_snapshot 
                     WHERE contributor_id = :contributor_id 
                       AND repo_id = :repo_id 
                       AND snapshot_date = :date",
                    [
                        'contributor_id' => $contributor['id'],
                        'repo_id' => $repoId,
                        'date' => $today
                    ]
                );
                
                if (!$stats) {
                    $githubContributor = $this->paginate("repos/{$owner}/{$repo}/stats/contributors");
                    $contributorData = current(array_filter($githubContributor, 
                        fn($c) => $c['author']['login'] === $contributor['github_username']));
                    
                    if ($contributorData) {
                        $stats = [
                            'contributor_id' => $contributor['id'],
                            'repo_id' => $repoId,
                            'snapshot_date' => $today,
                            'commits' => $contributorData['total'],
                            'prs_opened' => count($this->paginate("repos/{$owner}/{$repo}/pulls", 
                                ['state' => 'all', 'creator' => $contributor['github_username']])),
                            'reviews' => $this->getUserReviewCount($owner, $repo, $contributor['github_username'])
                        ];
                        
                        $this->db->insert('contributor_stats_snapshot', $stats);
                    }
                }
                
                if ($stats) {
                    $developers[] = [
                        'name' => $contributor['github_username'],
                        'commits' => (int)$stats['commits'],
                        'prs' => (int)$stats['prs_opened'],
                        'reviews' => (int)$stats['reviews']
                    ];
                }
            }
            
            // If no developers from database, fall back to API
            if (empty($developers)) {
                $contributors = $this->paginate("repos/{$owner}/{$repo}/stats/contributors");
                
                foreach ($contributors as $contributor) {
                    $login = $contributor['author']['login'];
                    $developers[] = [
                        'name' => $login,
                        'commits' => $contributor['total'],
                        'prs' => count($this->paginate("repos/{$owner}/{$repo}/pulls", 
                            ['state' => 'all', 'creator' => $login], true)),
                        'reviews' => $this->getUserReviewCount($owner, $repo, $login)
                    ];
                }
            }
        } catch (\Exception $e) {
            // Fall back to API-only approach
            $contributors = $this->paginate("repos/{$owner}/{$repo}/stats/contributors");
            
            $developers = array_map(function($contributor) use ($owner, $repo) {
                $login = $contributor['author']['login'];
                return [
                    'name' => $login,
                    'commits' => $contributor['total'],
                    'prs' => count($this->paginate("repos/{$owner}/{$repo}/pulls", 
                        ['state' => 'all', 'creator' => $login], true)),
                    'reviews' => $this->getUserReviewCount($owner, $repo, $login)
                ];
            }, $contributors);
        }
        
        // Sort and return top 4
        usort($developers, fn($a, $b) => $b['commits'] - $a['commits']);
        return array_slice($developers, 0, 4);
    }

    public function getRecentActivity($owner, $repo, $limit = 10)
    {
        // This method fetches real-time data, so we don't cache it
        $activities = [];

        // Get recent commits
        $commits = $this->paginate("repos/{$owner}/{$repo}/commits", [], false, $limit);
        foreach ($commits as $commit) {
            $activities[] = [
                'type' => 'commit',
                'actor' => $commit['commit']['author']['name'] ?? 'Unknown',
                'action' => "pushed " . $this->getCommitCount($commit) . " to " . substr($commit['commit']['message'], 0, 50),
                'date' => $commit['commit']['author']['date'],
                'link' => $commit['html_url']
            ];
        }

        // Get recent PRs
        $prs = $this->paginate("repos/{$owner}/{$repo}/pulls", 
            ['state' => 'all', 'sort' => 'updated', 'direction' => 'desc'], false, $limit);
        foreach ($prs as $pr) {
            $activities[] = [
                'type' => 'pull_request',
                'actor' => $pr['user']['login'] ?? 'Unknown',
                'action' => "{$pr['state']} a pull request in {$pr['base']['ref']}",
                'date' => $pr['updated_at'],
                'link' => $pr['html_url']
            ];
        }

        // Get recent reviews
        $recentPRs = $this->paginate("repos/{$owner}/{$repo}/pulls", 
            ['state' => 'all', 'sort' => 'updated', 'direction' => 'desc'], false, 20);
        foreach ($recentPRs as $pr) {
            $reviews = $this->paginate("repos/{$owner}/{$repo}/pulls/{$pr['number']}/reviews");
            foreach ($reviews as $review) {
                $activities[] = [
                    'type' => 'review',
                    'actor' => $review['user']['login'] ?? 'Unknown',
                    'action' => "reviewed pull request #{$pr['number']}",
                    'date' => $review['submitted_at'],
                    'link' => $review['html_url']
                ];
                if (count($activities) >= $limit * 3) break 2;
            }
        }

        // Sort and limit
        usort($activities, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
        return array_slice($activities, 0, $limit);
    }

    // Private helper methods
    private function getOrCreateRepo($owner, $repo)
    {
        $repoUrl = "https://github.com/{$owner}/{$repo}";
        
        // Try to find existing repo
        $existingRepo = $this->db->selectOne(
            "SELECT * FROM repos WHERE url = :url",
            ['url' => $repoUrl]
        );
        
        if ($existingRepo) {
            // Return existing repo with flag indicating it already exists
            return [
                'data' => $existingRepo,
                'is_new' => false
            ];
        }
        
        // Find user by GitHub ownership using the new github_ownerships table
        $ownershipData = $this->db->selectOne(
            "SELECT u.* FROM users u
             JOIN github_ownerships go ON u.id = go.user_id
             WHERE go.owner = :owner",
            ['owner' => $owner]
        );
        
        // Create repo (owner_id can be null if no user owns this GitHub account)
        $this->db->insert('repos', [
            'name' => $owner . '/' . $repo,
            'url' => $repoUrl,
            'owner_id' => $ownershipData['id'] ?? null,
            'last_updated' => date('Y-m-d H:i:s')
        ]);
        
        $repoData = $this->db->selectOne(
            "SELECT * FROM repos WHERE url = :url",
            ['url' => $repoUrl]
        );
        
        // Create user-repo relationship if user owns this GitHub account
        if ($ownershipData && $repoData) {
            try {
                $this->db->insert('user_has_repo', [
                    'user_id' => $ownershipData['id'],
                    'repo_id' => $repoData['id']
                ]);
            } catch (\Exception $e) {
                // Ignore duplicate key errors
            }
        }
        
        // Return new repo with flag indicating it's new
        return [
            'data' => $repoData,
            'is_new' => true
        ];
    }
    
    private function calculatePeriodChange($repoId)
    {
        try {
            // Changed: Get current week and last week data instead of last week and two weeks ago
            $today = date('Y-m-d');
            $lastWeek = date('Y-m-d', strtotime('-7 days'));
            
            // Get latest snapshot for current week (today)
            $currentWeekStats = $this->db->selectOne(
                "SELECT * FROM repo_stats_snapshot 
                 WHERE repo_id = :repo_id AND snapshot_date = :date",
                ['repo_id' => $repoId, 'date' => $today]
            );
            
            // If no current week stats, get the latest available
            if (!$currentWeekStats) {
                $currentWeekStats = $this->db->selectOne(
                    "SELECT * FROM repo_stats_snapshot 
                     WHERE repo_id = :repo_id
                     ORDER BY snapshot_date DESC LIMIT 1",
                    ['repo_id' => $repoId]
                );
            }
            
            // Get stats from last week
            $lastWeekStats = $this->db->selectOne(
                "SELECT * FROM repo_stats_snapshot 
                 WHERE repo_id = :repo_id AND snapshot_date <= :date 
                 ORDER BY snapshot_date DESC LIMIT 1",
                ['repo_id' => $repoId, 'date' => $lastWeek]
            );
            
            $metrics = ['commits', 'open_prs', 'issues', 'reviews'];
            $changes = [];
            
            foreach ($metrics as $metric) {
                if ($currentWeekStats && $lastWeekStats && $lastWeekStats[$metric] > 0) {
                    $changes[$metric] = round(
                        (($currentWeekStats[$metric] - $lastWeekStats[$metric]) / $lastWeekStats[$metric]) * 100, 
                        1
                    );
                } else {
                    $changes[$metric] = 0;
                }
            }
            
            return $changes;
        } catch (\Exception $e) {
            // Fallback to API-based calculation
            return $this->calculatePeriodChangeFromAPI('', '');
        }
    }
    
    private function calculatePeriodChangeFromAPI($owner, $repo)
    {
        $metrics = ['commits', 'prs', 'code_reviews', 'issues'];
        $changes = [];
        
        foreach ($metrics as $metric) {
            try {
                // Changed: Get current week (offset 0) and last week (offset -1)
                $currentWeek = $this->getMetricCount($metric, $owner, $repo, 0);
                $lastWeek = $this->getMetricCount($metric, $owner, $repo, -1);
                
                $changes[$metric] = $lastWeek > 0 ? 
                    round((($currentWeek - $lastWeek) / $lastWeek) * 100, 1) : 0;
            } catch (\Exception $e) {
                $changes[$metric] = 0;
            }
        }
        
        return $changes;
    }

    private function getMetricCount($metric, $owner, $repo, $weekOffset)
    {
        $range = $this->getWeekRange($weekOffset);
        
        switch ($metric) {
            case 'commits':
                return $this->getDateRangeCount("repos/{$owner}/{$repo}/commits", 
                    $range['start'], $range['end']);
            case 'prs':
                return $this->getDateRangeCount("repos/{$owner}/{$repo}/pulls", 
                    $range['start'], $range['end']);
            case 'code_reviews':
                return $this->getReviewsInDateRange($owner, $repo, 
                    $range['start'], $range['end']);
            case 'issues':
                return $this->getDateRangeCount("repos/{$owner}/{$repo}/issues", 
                    $range['start'], $range['end'], fn($i) => !isset($i['pull_request']));
        }
    }

    private function getWeekRange($weekNumber)
    {
        // Changed: Updated to handle current week (0) and previous weeks
        // weekNumber = 0 is current week, -1 is last week, -2 is two weeks ago, etc.
        $startOffset = $weekNumber * 7;
        $endOffset = ($weekNumber * 7) + 6;
        
        // Calculate the start of the week (Monday)
        $now = new \DateTime();
        $currentDayOfWeek = (int)$now->format('N'); // 1 (Monday) through 7 (Sunday)
        
        // Adjust to get the start (Monday) of the current week
        $mondayOffset = -($currentDayOfWeek - 1) + $startOffset;
        $start = new \DateTime();
        $start->modify("$mondayOffset days");
        
        // End is Sunday of the same week (6 days after Monday)
        $end = clone $start;
        $end->modify('+6 days');
        
        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d')
        ];
    }

    // Keep all the existing GitHub API methods (paginate, makeRequest, etc.)
    private function paginate($endpoint, $params = [], $countOnly = false, $maxItems = null)
    {
        $allItems = [];
        $page = 1;
        $perPage = min($maxItems ?? 100, 100);

        do {
            $params['page'] = $page;
            $params['per_page'] = $perPage;
            
            $items = $this->makeRequest($endpoint, $params);
            if (empty($items)) break;
            
            if ($countOnly) {
                return count($items);
            }
            
            $allItems = array_merge($allItems, $items);
            $page++;
            
            if ($maxItems && count($allItems) >= $maxItems) {
                return array_slice($allItems, 0, $maxItems);
            }
        } while (count($items) == $perPage);

        return $allItems;
    }

    private function countInDateRange($endpoint, $startTimestamp, $endTimestamp)
    {
        $startDate = date('Y-m-d', $startTimestamp);
        $endDate = date('Y-m-d', $endTimestamp);
        
        $items = $this->paginate($endpoint, ['state' => 'all', 'since' => $startDate]);
        
        return count(array_filter($items, function($item) use ($startDate, $endDate) {
            $itemDate = date('Y-m-d', strtotime($item['created_at']));
            return $itemDate >= $startDate && $itemDate < $endDate;
        }));
    }

    private function getCodeReviewsCount($owner, $repo)
    {
        $since = date('Y-m-d', strtotime('-30 days'));
        $recentPRs = $this->paginate("repos/{$owner}/{$repo}/pulls", 
            ['state' => 'all', 'since' => $since]);
        
        $totalReviews = 0;
        foreach ($recentPRs as $pr) {
            $reviews = $this->paginate("repos/{$owner}/{$repo}/pulls/{$pr['number']}/reviews");
            $totalReviews += count($reviews);
        }
        
        return $totalReviews;
    }

    private function getDateRangeCount($endpoint, $startDate, $endDate, $filter = null)
    {
        $items = $this->paginate($endpoint, [
            'state' => 'all',
            'since' => $startDate . 'T00:00:00Z',
            'until' => $endDate . 'T23:59:59Z'
        ]);
        
        if ($filter) {
            $items = array_filter($items, $filter);
        }
        
        return count(array_filter($items, function($item) use ($startDate, $endDate) {
            $itemDate = date('Y-m-d', strtotime($item['created_at']));
            return $itemDate >= $startDate && $itemDate <= $endDate;
        }));
    }

    private function getReviewsInDateRange($owner, $repo, $startDate, $endDate)
    {
        $extendedStart = date('Y-m-d', strtotime($startDate . ' -14 days'));
        $prs = $this->paginate("repos/{$owner}/{$repo}/pulls", 
            ['state' => 'all', 'since' => $extendedStart]);
        
        $totalReviews = 0;
        foreach ($prs as $pr) {
            $reviews = $this->paginate("repos/{$owner}/{$repo}/pulls/{$pr['number']}/reviews");
            $totalReviews += count(array_filter($reviews, function($review) use ($startDate, $endDate) {
                $reviewDate = date('Y-m-d', strtotime($review['submitted_at']));
                return $reviewDate >= $startDate && $reviewDate <= $endDate;
            }));
        }
        
        return $totalReviews;
    }

    private function getReviewCountInWeek($owner, $repo, $weekTimestamp)
    {
        $startDate = date('Y-m-d', $weekTimestamp);
        $endDate = date('Y-m-d', $weekTimestamp + 604800);
        
        $prs = $this->paginate("repos/{$owner}/{$repo}/pulls", 
            ['state' => 'all', 'since' => $startDate]);
        
        $totalReviews = 0;
        foreach ($prs as $pr) {
            if (date('Y-m-d', strtotime($pr['created_at'])) >= $startDate && 
                date('Y-m-d', strtotime($pr['created_at'])) < $endDate) {
                $reviews = $this->paginate("repos/{$owner}/{$repo}/pulls/{$pr['number']}/reviews");
                $totalReviews += count(array_filter($reviews, function($review) use ($startDate, $endDate) {
                    $reviewDate = date('Y-m-d', strtotime($review['submitted_at']));
                    return $reviewDate >= $startDate && $reviewDate < $endDate;
                }));
            }
        }
        
        return $totalReviews;
    }

    private function getUserReviewCount($owner, $repo, $username)
    {
        $since = date('Y-m-d', strtotime('-90 days'));
        $allPRs = $this->paginate("repos/{$owner}/{$repo}/pulls", 
            ['state' => 'all', 'since' => $since]);
        
        $totalReviews = 0;
        foreach ($allPRs as $pr) {
            $reviews = $this->paginate("repos/{$owner}/{$repo}/pulls/{$pr['number']}/reviews");
            $totalReviews += count(array_filter($reviews, 
                fn($review) => $review['user']['login'] === $username));
        }
        
        return $totalReviews;
    }

    private function getCommitCount($commit)
    {
        try {
            $commitDetails = $this->makeRequest("repos/{$commit['repository']['full_name']}/commits/{$commit['sha']}");
            $fileCount = count($commitDetails['files'] ?? []);
            
            if ($fileCount === 0) return "1 commit";
            if ($fileCount === 1) return "1 file changed";
            return "{$fileCount} files changed";
        } catch (\Exception $e) {
            return "1 commit";
        }
    }

    private function makeRequest($endpoint, $params = [])
    {
        try {
            $response = $this->client->get($endpoint, ['query' => $params]);
            
            if ($response->getStatusCode() === 202) {
                sleep(2);
                return $this->makeRequest($endpoint, $params);
            }
            
            return json_decode($response->getBody(), true) ?? [];
        } catch (\Exception $e) {
            throw new \Exception("GitHub API request failed: " . $e->getMessage());
        }
    }
}
