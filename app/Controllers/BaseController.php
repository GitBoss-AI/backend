<?php
namespace App\Controllers;
use App\Services\GitHubService;

abstract class BaseController
{
    protected $githubService;

    public function __construct()
    {
        $this->githubService = new GitHubService();
    }

    /**
     * Validate required parameters and return them
     * 
     * @param array $required Required parameter names
     * @param array $optional Optional parameter names with default values
     * @return array|null Returns parameters array or null if validation fails
     */
    protected function validateParameters(array $required = [], array $optional = [])
    {
        $params = [];
        
        // Check required parameters
        foreach ($required as $param) {
            // Fixed: Use correct environment variable names
            $envMap = [
                'owner' => 'GITHUB_OWNER',
                'repo' => 'GITHUB_REPO'
            ];
            
            $envName = $envMap[$param] ?? "GITHUB_" . strtoupper($param);
            $value = $_GET[$param] ?? $_ENV[$envName] ?? null;
            
            if (!$value) {
                $this->sendErrorResponse(
                    400, 
                    'Owner and repo parameters are required'
                );
                return null;
            }
            
            $params[$param] = $value;
        }
        
        // Process optional parameters
        foreach ($optional as $param => $default) {
            $paramValue = $_GET[$param] ?? $default;
            
            // Cast to int if it's a numeric parameter like 'limit'
            if ($param === 'limit') {
                $paramValue = (int)$paramValue;
            }
            
            $params[$param] = $paramValue;
        }
        
        return $params;
    }

    /**
     * Send JSON response with success status
     * 
     * @param mixed $data Data to send
     * @param int $statusCode HTTP status code
     */
    protected function sendSuccessResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Send JSON error response
     * 
     * @param int $statusCode HTTP status code
     * @param string $message Error message
     */
    protected function sendErrorResponse($statusCode, $message)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $message
        ]);
    }

    /**
     * Execute an action with try-catch error handling
     * 
     * @param callable $action Action to execute
     */
    protected function executeWithErrorHandling(callable $action)
    {
        try {
            $action();
        } catch (\Exception $e) {
            $this->sendErrorResponse(500, $e->getMessage());
        }
    }

    /**
     * Standard method to get owner and repo
     * 
     * @return array|null Returns [owner, repo] or null if validation fails
     */
    protected function getOwnerAndRepo()
    {
        $params = $this->validateParameters(['owner', 'repo']);
        
        if (!$params) {
            return null;
        }
        
        return [$params['owner'], $params['repo']];
    }
}