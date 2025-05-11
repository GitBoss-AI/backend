<?php
namespace App\Services;

use DateTime;

class TeamService extends BaseService {
    public function getTimeline(int $repoId, string $timeWindow, string $groupBy = 'month') {
        $validGroups = ['week', 'month', 'quarter'];
        if (!in_array($groupBy, $validGroups)) {
            throw new \Exception("Invalid group_by value.");
        }

        try {
            $startDate = $this->getStartDate($timeWindow);
        } catch (\Exception $e) {
            throw new \Exception("Invalid time_window: " . $e->getMessage());
        }

        $rows = $this->db->selectAll(
            "SELECT snapshot_date, commits, open_prs, reviews
             FROM repo_stats
             WHERE repo_id = :repo_id AND snapshot_date >= :start_date
             ORDER BY snapshot_date ASC",
            ['repo_id' => $repoId, 'start_date' => $startDate]
        );

        $buckets = [];

        foreach ($rows as $r) {
            $date = new DateTime($r['snapshot_date']);

            $key = match ($groupBy) {
                'week'    => 'W' . $date->format('W'),
                'month'   => $date->format('M'),
                'quarter' => 'Q' . ceil((int)$date->format('n') / 3),
            };

            if (!isset($buckets[$key])) {
                $buckets[$key] = ['name' => $key, 'commits' => 0, 'prs' => 0, 'reviews' => 0];
            }

            $buckets[$key]['commits'] += $r['commits'];
            $buckets[$key]['prs']     += $r['open_prs'];
            $buckets[$key]['reviews'] += $r['reviews'];
        }

        return array_values($buckets);
    }
}