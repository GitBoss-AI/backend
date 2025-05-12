<?php
namespace App\Services;

use App\Logger\Logger;

class TeamService extends BaseService {

    private $logFile = 'teamservice';

    public function getTimeline(int $repoId, string $timeWindow, string $groupBy = 'month') {
        Logger::info($this->logFile, "Generating timeline for repo_id=$repoId, window=$timeWindow, group_by=$groupBy");

        $validGroups = ['week', 'month', 'quarter'];
        if (!in_array($groupBy, $validGroups)) {
            Logger::error($this->logFile, "Invalid group_by: $groupBy");
            throw new \Exception("Invalid group_by value.");
        }

        try {
            $startDate = $this->getStartDate($timeWindow);
        } catch (\Exception $e) {
            Logger::error($this->logFile, "Invalid timeWindow: " . $e->getMessage());
            throw new \Exception("Invalid time_window: " . $e->getMessage());
        }

        $rows = $this->db->selectAll(
            "SELECT snapshot_date, commits, open_prs, reviews
             FROM repo_stats
             WHERE repo_id = :repo_id AND snapshot_date >= :start_date
             ORDER BY snapshot_date ASC",
            ['repo_id' => $repoId, 'start_date' => $startDate]
        );

        Logger::info($this->logFile, "Fetched " . count($rows) . " rows from repo_stats");

        $buckets = [];

        foreach ($rows as $r) {
            $date = new \DateTime($r['snapshot_date']);

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

        Logger::info($this->logFile, "Timeline generation complete");
        return array_values($buckets);
    }
}