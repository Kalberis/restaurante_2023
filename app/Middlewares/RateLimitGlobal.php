<?php

namespace Middlewares;

use Core\Middleware;
use Core\Logger;

/**
 * Middleware que limita requisições por IP
 * Padrão: 100 requisições por minuto
 */
class RateLimitGlobal extends Middleware
{
    private int $max_requests = 100;
    private int $window_minutes = 1;
    private string $storage_file;

    public function __construct(int $max_requests = 100, int $window_minutes = 1)
    {
        $this->max_requests = $max_requests;
        $this->window_minutes = $window_minutes;
        $this->storage_file = dirname(__DIR__) . '/storage/rate_limit.json';
        
        if (!is_dir(dirname($this->storage_file))) {
            mkdir(dirname($this->storage_file), 0755, true);
        }
    }

    public function check(): bool
    {
        $ip = $this->getClientIP();
        $requests = $this->getRequests($ip);

        if ($requests >= $this->max_requests) {
            Logger::getInstance()->warning('Rate limit excedido', ['ip' => $ip]);
            return false;
        }

        return true;
    }

    public function handle(): void
    {
        http_response_code(429);
        die(json_encode([
            'error' => 'Too Many Requests',
            'message' => "Limite de {$this->max_requests} requisições por {$this->window_minutes} minuto(s) excedido",
            'retry_after' => $this->window_minutes * 60
        ]));
    }

    /**
     * Incrementa contador de requisição
     */
    public function recordRequest(): void
    {
        $ip = $this->getClientIP();
        $data = $this->loadData();

        if (!isset($data[$ip])) {
            $data[$ip] = ['count' => 0, 'timestamp' => time()];
        }

        // Reseta se passou da janela
        if (time() - $data[$ip]['timestamp'] > ($this->window_minutes * 60)) {
            $data[$ip] = ['count' => 0, 'timestamp' => time()];
        }

        $data[$ip]['count']++;
        $this->saveData($data);
    }

    /**
     * Obtém número de requisições do IP
     */
    private function getRequests(string $ip): int
    {
        $data = $this->loadData();

        if (!isset($data[$ip])) {
            return 0;
        }

        // Se passou da janela, reseta
        if (time() - $data[$ip]['timestamp'] > ($this->window_minutes * 60)) {
            return 0;
        }

        return (int)$data[$ip]['count'];
    }

    /**
     * Obtém IP do cliente
     */
    private function getClientIP(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Carrega dados de rate limiting
     */
    private function loadData(): array
    {
        if (!file_exists($this->storage_file)) {
            return [];
        }

        $json = file_get_contents($this->storage_file);
        return json_decode($json, true) ?? [];
    }

    /**
     * Salva dados de rate limiting
     */
    private function saveData(array $data): void
    {
        // Limpa entradas antigas
        $cutoff = time() - ($this->window_minutes * 60 * 5); // Mantém últimas 5 janelas
        foreach ($data as $ip => $info) {
            if ($info['timestamp'] < $cutoff) {
                unset($data[$ip]);
            }
        }

        file_put_contents($this->storage_file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
