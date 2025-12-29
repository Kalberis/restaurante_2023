<?php

namespace Core;

/**
 * Sistema de rate limiting para proteção contra força bruta
 */
class RateLimiter
{
    private string $identifier;
    private int $max_attempts;
    private int $window_minutes;
    private string $cache_key;

    public function __construct(string $identifier, int $max_attempts = 5, int $window_minutes = 15)
    {
        $this->identifier = $identifier;
        $this->max_attempts = $max_attempts;
        $this->window_minutes = $window_minutes;
        $this->cache_key = 'rate_limit_' . hash('sha256', $identifier);
    }

    /**
     * Verifica se o identificador excedeu limite de tentativas
     */
    public function isLimited(): bool
    {
        $attempts = $this->getAttempts();
        return $attempts >= $this->max_attempts;
    }

    /**
     * Incrementa contador de tentativas
     */
    public function recordAttempt(): void
    {
        $session = Session::getInstance();
        $data = $session->{$this->cache_key} ?? ['count' => 0, 'timestamp' => time()];

        // Se passou a janela de tempo, reseta
        if (time() - $data['timestamp'] > ($this->window_minutes * 60)) {
            $data = ['count' => 0, 'timestamp' => time()];
        }

        $data['count']++;
        $session->{$this->cache_key} = $data;

        // Log de tentativa
        Logger::getInstance()->warning('Tentativa de login registrada', [
            'identifier' => $this->identifier,
            'attempts' => $data['count'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    /**
     * Obtém número de tentativas atuais
     */
    public function getAttempts(): int
    {
        $session = Session::getInstance();
        $data = $session->{$this->cache_key} ?? ['count' => 0, 'timestamp' => time()];

        // Se passou a janela de tempo, reseta
        if (time() - $data['timestamp'] > ($this->window_minutes * 60)) {
            return 0;
        }

        return (int)$data['count'];
    }

    /**
     * Retorna minutos restantes até resetar o limite
     */
    public function getMinutesRemaining(): int
    {
        $session = Session::getInstance();
        $data = $session->{$this->cache_key} ?? null;

        if (!$data) {
            return 0;
        }

        $elapsed = time() - $data['timestamp'];
        $remaining = ($this->window_minutes * 60) - $elapsed;

        return max(0, (int)ceil($remaining / 60));
    }

    /**
     * Reseta as tentativas (após login bem-sucedido)
     */
    public function reset(): void
    {
        $session = Session::getInstance();
        unset($session->{$this->cache_key});
    }
}
