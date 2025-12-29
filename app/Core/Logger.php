<?php

namespace Core;

use DateTime;
use DateTimeZone;

/**
 * Sistema de logging estruturado
 */
class Logger
{
    private static $instance;
    private string $log_file;
    private array $log_levels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];

    private function __construct()
    {
        $log_dir = BASE_PATH . '/storage/logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        $this->log_file = $log_dir . '/app-' . date('Y-m-d') . '.log';
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registra mensagem no log
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))->format('Y-m-d H:i:s.u');
        
        $context_str = '';
        if (!empty($context)) {
            $context_str = ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        $log_line = sprintf(
            "[%s] %s - %s%s\n",
            $timestamp,
            $level,
            $message,
            $context_str
        );

        file_put_contents($this->log_file, $log_line, FILE_APPEND | LOCK_EX);

        // Limita tamanho do log (máx 10MB)
        if (filesize($this->log_file) > 10485760) {
            $this->rotateLogs();
        }
    }

    /**
     * Rotaciona logs quando ficam grandes
     */
    private function rotateLogs(): void
    {
        $backup = $this->log_file . '.' . time();
        rename($this->log_file, $backup);
        
        // Remove logs com mais de 30 dias
        $log_dir = dirname($this->log_file);
        foreach (glob($log_dir . '/app-*.log.*') as $old_log) {
            if (time() - filemtime($old_log) > 2592000) { // 30 dias
                unlink($old_log);
            }
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }
}
