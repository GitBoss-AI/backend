<?php
namespace App\Logger;

class Logger {
    private static function getLogFilePath(string $name): string {
        $logDir = '/var/www/gitboss-ai/backend-dev/log';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        return "$logDir/{$name}.log";
    }

    public static function info(string $name, string $message): void {
        self::write($name, "INFO", $message);
    }

    public static function error(string $name, string $message): void {
        self::write($name, "ERROR", $message);
    }

    private static function write(string $name, string $level, string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] [$level] $message\n";
        file_put_contents(self::getLogFilePath($name), $line, FILE_APPEND);
    }
}
