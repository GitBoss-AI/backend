<?php
namespace App\Controllers;

use App\Services\TeamService;

class TeamController {
    private $teamService;

    public function __construct() {
        $this->teamService = new TeamService();
    }

    public function getTimeline() {
        header('Content-type: application/json');

        $repoId = $_GET['repo_id'] ?? null;
        $groupBy = $_GET['group_by'] ?? 'month'; // default
        $timeWindow = $_GET['time_window'] ?? '4m';

        if (!$repoId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing repo_id']);
            return;
        }

        try {
            $timeline = $this->teamService->getTimeline((int)$repoId, $timeWindow, $groupBy);
            echo json_encode($timeline);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}