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
$repoWorker = "$php $projectRoot/app/Cronjob/Workers/RepoStatsWorker.php >> /var/www/gitboss-ai/backend-dev/log/repoworker.log 2>&1";
$contribWorker = "$php $projectRoot/app/Cronjob/Workers/ContributorStatsWorker.php >> /var/www/gitboss-ai/backend-dev/log/contribworker.log 2>&1";
$activityWorker = "$php $projectRoot/app/Cronjob/Workers/ContributorActivityWorker.php >> /var/www/gitboss-ai/backend-dev/log/activityworker.log 2>&1";

// Register them
$cron = new CronManager();
$cron->addJob('0 * * * *', $repoWorker);        // at every minute 0
$cron->addJob('0 * * * *', $contribWorker);     // at every minute 0
$cron->addJob('10 * * * *', $activityWorker);   // at every minute 10 -> after contributions are calculated
$cron->install();
