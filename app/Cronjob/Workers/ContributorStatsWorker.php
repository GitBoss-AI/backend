<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Services\ContributorService;
use App\Database\DB;

echo "[*] Running ContributorStatsWorker...\n";

$contributorService = new ContributorService();
$db = DB::getInstance();

$now = date('Y-m-d H:i:s');

$rows = $db->selectAll("
    SELECT 
        rhc.repo_id,
        rhc.last_seen,
        c.github_username,
        r.owner,
        r.name
    FROM repo_has_contributor rhc
    JOIN contributors c ON c.id = rhc.contributor_id
    JOIN repos r ON r.id = rhc.repo_id
");

foreach ($rows as $row) {
    $repoId = $row['repo_id'];
    $username = $row['github_username'];
    $owner = $row['owner'];
    $name = $row['name'];
    $lastSeen = $row['last_seen'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));

    echo "Syncing $username in $owner/$name...\n";

    try {
        $contributor = $db->selectOne(
            "SELECT id FROM contributors WHERE github_username = :username",
            ['username' => $username]
        );

        if (!$contributor) {
            echo "Contributor not found in DB: $username\n";
            continue;
        }

        $contributorId = $contributor['id'];
        $since = (new DateTime($lastSeen))->format(DateTime::ATOM);

        // Fetch deltas
        $commitDelta = $contributorService->getContributorCommitCount($username, $owner, $name, $since);
        $reviewDelta = $contributorService->getContributorReviewCount($username, $owner, $name, $since);

        // Get latest existing snapshot
        $latest = $db->selectOne(
            "SELECT * FROM contributor_stats
             WHERE contributor_id = :cid AND repo_id = :rid
             ORDER BY snapshot_date DESC
             LIMIT 1",
            ['cid' => $contributorId, 'rid' => $repoId]
        );

        $existingCommits = $latest['commits'] ?? 0;
        $existingReviews = $latest['reviews'] ?? 0;

        $stats = [
            'contributor_id' => $contributorId,
            'repo_id'        => $repoId,
            'snapshot_date'  => $now,
            'commits'        => $existingCommits + $commitDelta,
            'prs_opened'     => $contributorService->getContributorPrCount($username, $owner, $name),
            'reviews'        => $existingReviews + $reviewDelta,
        ];

        $db->insert('contributor_stats', $stats);

        // Update last_seen
        $db->execute(
            "UPDATE repo_has_contributor 
             SET last_seen = :now 
             WHERE repo_id = :repo_id AND contributor_id = :contributor_id",
            ['now' => $now, 'repo_id' => $repoId, 'contributor_id' => $contributorId]
        );

        echo "Snapshot written for $username in $owner/$name\n";

    } catch (\Exception $e) {
        echo "Error for $username in $owner/$name: " . $e->getMessage() . "\n";
    }
}

echo "ContributorStatsWorker complete.\n";
