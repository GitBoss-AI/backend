<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database\DB;

echo "Running ContributorActivityWorker...\n";

$db = DB::getInstance();
$repos = $db->selectAll("SELECT id FROM repos");

foreach ($repos as $repo) {
    $repoId = $repo['id'];

    $contributors = $db->selectAll("
        SELECT c.id, c.github_username
        FROM repo_has_contributor rhc
        JOIN contributors c ON rhc.contributor_id = c.id
        WHERE rhc.repo_id = :repo_id",
        ['repo_id' => $repoId]
    );

    foreach ($contributors as $contributor) {
        $cid = $contributor['id'];
        $username = $contributor['github_username'];

        $snapshots = $db->selectAll(
            "SELECT * FROM contributor_stats
             WHERE contributor_id = :cid AND repo_id = :rid
             ORDER BY snapshot_date DESC
             LIMIT 2",
            ['cid' => $cid, 'rid' => $repoId]
        );

        if (count($snapshots) < 2) continue;

        [$latest, $previous] = $snapshots;

        $deltas = [
            'commit' => $latest['commits']      - $previous['commits'],
            'pr'     => $latest['prs_opened']   - $previous['prs_opened'],
            'review' => $latest['reviews']      - $previous['reviews'],
        ];

        foreach ($deltas as $type => $qty) {
            if ($qty > 0) {
                $db->insert('contributor_activity_events', [
                    'contributor_id' => $cid,
                    'repo_id' => $repoId,
                    'event_type' => $type,
                    'quantity' => $qty,
                    'occurred_at' => date('Y-m-d H:i:s'),
                ]);
                echo "Inserted $qty $type(s) for $username in repo $repoId\n";
            }
        }
    }
}

echo "ContributorActivityWorker complete.\n";
