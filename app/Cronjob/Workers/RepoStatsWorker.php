<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Services\RepoService;
use App\Database\DB;

echo "[*] Running RepoStatsWorker...\n";

$repoService = new RepoService();
$db = DB::getInstance();

$repos = $db->selectAll("SELECT id, owner, name FROM repos");

foreach ($repos as $repo) {
    $repoId = $repo['id'];
    $owner = $repo['owner'];
    $name = $repo['name'];

    echo "Syncing $owner/$name...\n";

    try {
        $stats = [
            'repo_id' => $repoId,
            'snapshot_date' => date('Y-m-d'),
            'commits'       => $repoService->getRepoCommitCount($owner, $name),
            'open_prs'      => $repoService->getRepoOpenPrCount($owner, $name),
            'merged_prs'    => $repoService->getRepoMergedPrCount($owner, $name),
            'open_issues'   => $repoService->getRepoOpenIssueCount($owner, $name),
            'reviews'       => $repoService->getRepoReviewCount($owner, $name)
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
