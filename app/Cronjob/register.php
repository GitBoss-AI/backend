<?php

require_once __DIR__ . '/../Cronjob/CronManager.php';

use App\Cronjob\CronManager;

spl_autoload_register(function ($class) {
    $path = __DIR__ . '/../../' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

$php = trim(shell_exec('which php'));
$projectRoot = realpath(__DIR__ . '/../../');

// Define worker paths
$repoWorker = "$php $projectRoot/app/Cronjob/RepoStatsWorker.php >> /var/log/gitboss-repoworker.log 2>&1";
$contribWorker = "$php $projectRoot/app/Cronjob/ContributorWorker.php >> /var/log/gitboss-contribworker.log 2>&1";

// Register them
$cron = new CronManager();
$cron->addJob('0 1 * * *', $repoWorker);        // sync at 01:00
$cron->addJob('0 1 * * *', $contribWorker);     // sync at 01:00
$cron->install();
