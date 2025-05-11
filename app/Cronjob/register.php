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
$repoWorker = "$php $projectRoot/app/Cronjob/RepoWorker.php >> /var/log/gitboss-repo.log 2>&1";
$contribWorker = "$php $projectRoot/app/Cronjob/ContributorWorker.php >> /var/log/gitboss-contrib.log 2>&1";

// Register them
$cron = new CronManager();
$cron->addJob('0 3 * * *', $repoWorker);         // Repo sync at 03:00
$cron->addJob('30 3 * * *', $contribWorker);     // Contributor sync at 03:30
$cron->install();
