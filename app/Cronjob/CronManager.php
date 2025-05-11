<?php

namespace App\Cronjob;

class CronManager
{
    private $workers = [];

    public function addJob(string $expression, string $command): void
    {
        $this->workers[] = "$expression $command";
    }

    public function install(): void
    {
        $existing = shell_exec('crontab -l 2>/dev/null');
        $existingLines = array_filter(explode("\n", $existing));
        $newLines = array_merge($existingLines, $this->workers);
        $unique = array_unique($newLines);

        $cronText = implode("\n", $unique) . "\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'cron_');
        file_put_contents($tmpFile, $cronText);

        exec("crontab $tmpFile");
        unlink($tmpFile);
    }
}
