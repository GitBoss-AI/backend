<?php
namespace App\Controllers;
use App\Services\GitHubService;

class TeamActivityController extends BaseController
{
    public function getTimeline()
    {
        $this->executeWithErrorHandling(function() {
            list($owner, $repo) = $this->getOwnerAndRepo();
            
            if ($owner && $repo) {
                $timeline = $this->githubService->getActivityTimeline($owner, $repo);
                $this->sendSuccessResponse($timeline);
            }
        });
    }
    
    public function getComparison()
    {
        $this->executeWithErrorHandling(function() {
            $params = $this->validateParameters(['owner', 'repo']);
            
            if ($params) {
                // Note: In your original code, repo was getting GITHUB_OWNER instead of GITHUB_REPO
                // Keeping it as is for consistency, but this might be a bug in the original
                $comparison = $this->githubService->getDeveloperComparison(
                    $params['owner'], 
                    $params['repo']
                );
                
                $this->sendSuccessResponse($comparison);
            }
        });
    }
}