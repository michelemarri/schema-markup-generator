<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Logger;

/**
 * Logger
 *
 * Simple file-based logger for debugging.
 *
 * @package flavor\SchemaMarkupGenerator\Logger
 * @author  Michele Marri <info@metodo.dev>
 */
class Logger
{
    /**
     * Log levels
     */
    public const DEBUG = 'DEBUG';
    public const INFO = 'INFO';
    public const WARNING = 'WARNING';
    public const ERROR = 'ERROR';

    /**
     * Log directory path
     */
    private string $logDir;

    /**
     * Whether debug mode is enabled
     */
    private bool $debugMode;

    /**
     * Current log file path
     */
    private string $logFile;

    public function __construct(string $logDir, bool $debugMode = false)
    {
        $this->logDir = $logDir;
        $this->debugMode = $debugMode;
        $this->logFile = $logDir . '/smg-' . date('Y-m-d') . '.log';

        // Ensure log directory exists
        if (!is_dir($this->logDir)) {
            wp_mkdir_p($this->logDir);
            file_put_contents($this->logDir . '/.htaccess', 'Deny from all');
            file_put_contents($this->logDir . '/index.php', '<?php // Silence is golden');
        }
    }

    /**
     * Log a debug message
     */
    public function debug(string $message, array $context = []): void
    {
        if ($this->debugMode) {
            $this->log(self::DEBUG, $message, $context);
        }
    }

    /**
     * Log an info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Log a warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Log an error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Write log entry
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . wp_json_encode($context);

        $logEntry = sprintf(
            "[%s] [%s] %s%s\n",
            $timestamp,
            $level,
            $message,
            $contextStr
        );

        error_log($logEntry, 3, $this->logFile);

        // Rotate logs if needed
        $this->rotateIfNeeded();
    }

    /**
     * Rotate old log files
     */
    private function rotateIfNeeded(): void
    {
        // Keep only last 7 days of logs
        $files = glob($this->logDir . '/smg-*.log');

        if ($files && count($files) > 7) {
            usort($files, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Delete oldest files
            $toDelete = count($files) - 7;
            for ($i = 0; $i < $toDelete; $i++) {
                @unlink($files[$i]);
            }
        }
    }

    /**
     * Get log file path
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * Get recent log entries
     *
     * @param int $lines Number of lines to return
     * @return array Log entries
     */
    public function getRecentEntries(int $lines = 50): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $file = file($this->logFile);
        return array_slice($file, -$lines);
    }

    /**
     * Clear log file
     */
    public function clear(): bool
    {
        if (file_exists($this->logFile)) {
            return file_put_contents($this->logFile, '') !== false;
        }
        return true;
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebugMode(): bool
    {
        return $this->debugMode;
    }
}

