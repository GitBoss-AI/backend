<?php
namespace App\Controllers;
use App\Services\GitHubService;

class RepositoryStatsController extends BaseController
{
    public function getStats()
    {
        $this->executeWithErrorHandling(function() {
            list($owner, $repo) = $this->getOwnerAndRepo();
            
            if ($owner && $repo) {
                $stats = $this->githubService->getRepositoryStats($owner, $repo);
                $this->sendSuccessResponse($stats);
            }
        });
    }
}