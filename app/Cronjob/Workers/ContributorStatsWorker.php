<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Services\ContributorService;
use App\Database\DB;

echo "[*] Running ContributorWorker...\n";

$contributorService = new ContributorService();
$db = DB::getInstance();

$today = date('Y-m-d');

$rows = $db->selectAll("
    SELECT 
        rhc.repo_id,
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

        $stats = [
            'contributor_id' => $contributorId,
            'repo_id'        => $repoId,
            'snapshot_date'  => date('Y-m-d H:i:s'),
            'commits'        => $contributorService->getContributorCommitCount($username, $owner, $name),
            'prs_opened'     => $contributorService->getContributorPrCount($username, $owner, $name),
            'reviews'        => $contributorService->getContributorReviewCount($username, $owner, $name),
        ];

        $db->insert('contributor_stats', $stats);

        // Update last_seen
        $db->execute(
            "UPDATE repo_has_contributor 
             SET last_seen = :today 
             WHERE repo_id = :repo_id AND contributor_id = :contributor_id",
            ['today' => $today, 'repo_id' => $repoId, 'contributor_id' => $contributorId]
        );

        echo "Snapshot written for $username in $owner/$name\n";

    } catch (\Exception $e) {
        echo "Error for $username in $owner/$name: " . $e->getMessage() . "\n";
    }
}

echo "ContributorStatsWorker complete.\n";
