<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Services\GithubClient;
use App\Services\RepoService;
use App\Database\DB;

echo "[*] Running RepoStatsWorker...\n";

$repoService = new RepoService();
$db = DB::getInstance();

$repos = $db->selectAll("SELECT id, owner, name FROM repos");

foreach ($repos as $repo) {
    $githubClient = new GithubClient();

    $repoId = $repo['id'];
    $owner = $repo['owner'];
    $name = $repo['name'];
    $lastUpdated = $repo['last_updated'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));

    echo "Syncing $owner/$name...\n";

    try {
        $since = (new DateTime($lastUpdated))->format(DateTime::ATOM);

        // Fetch deltas
        $commitDelta = count($githubClient->getPaginated("repos/$owner/$name/commits", [
            'since' => $since
        ]));
        $reviewDelta = $repoService->getRepoReviewCount($owner, $name, $since);


        // Get latest existing snapshot (total values)
        $latest = $db->selectOne(
            "SELECT * FROM repo_stats
             WHERE repo_id = :repo_id
             ORDER BY snapshot_date DESC
             LIMIT 1",
            ['repo_id' => $repoId]
        );

        $existingCommits = $latest['commits'] ?? 0;
        $existingReviews = $latest['reviews'] ?? 0;

        $stats = [
            'repo_id' => $repoId,
            'snapshot_date' => date('Y-m-d H:i:s'),
            'commits'       => $existingCommits + $commitDelta,
            'open_prs'      => $repoService->getRepoOpenPrCount($owner, $name),
            'open_issues'   => $repoService->getRepoOpenIssueCount($owner, $name),
            'reviews'       => $existingReviews + $reviewDelta
        ];

        $db->insert('repo_stats', $stats);

        $db->execute(
            "UPDATE repos SET last_updated = :now WHERE id = :id",
            ['now' => date('Y-m-d H:i:s'), 'id' => $repoId]
        );

        echo "Snapshot written for $owner/$name\n";
    } catch (\Exception $e) {
        echo "Failed: " . $e->getMessage() . "\n";
    }
}

echo "RepoStatsWorker complete.\n";
