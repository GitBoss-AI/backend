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
}