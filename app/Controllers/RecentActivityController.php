<?php
namespace App\Controllers;
use App\Services\GitHubService;

class RecentActivityController extends BaseController
{
    public function getRecentActivity()
    {
        $this->executeWithErrorHandling(function() {
            $params = $this->validateParameters(['owner', 'repo'], ['limit' => 10]);
            
            if ($params) {
                $activities = $this->githubService->getRecentActivity(
                    $params['owner'], 
                    $params['repo'], 
                    $params['limit']
                );
                
                $this->sendSuccessResponse($activities);
            }
        });
    }
}