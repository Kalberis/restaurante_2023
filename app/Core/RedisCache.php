<?php

namespace Core;

/**
 * Integração com Redis para cache de queries, sessions e rate limiting
 * Fallback para cache em memória se Redis não estiver disponível
 */
class RedisCache
{
    private static ?self $instance = null;
    private ?\Redis $redis = null;
    private bool $available = false;
    private string $prefix = 'restaurante_';

    private function __construct()
    {
        try {
            $this->redis = new \Redis();
            $host = $_ENV['REDIS_HOST'] ?? 'localhost';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
            $password = $_ENV['REDIS_PASSWORD'] ?? null;
            $db = (int)($_ENV['REDIS_DB'] ?? 0);

            if ($this->redis->connect($host, $port, 1)) {
                if ($password) {
                    $this->redis->auth($password);
                }
                $this->redis->select($db);
                $this->redis->setOption(\Redis::OPT_PREFIX, $this->prefix);
                $this->available = true;

                Logger::getInstance()->info('Conectado ao Redis', ['host' => $host, 'port' => $port]);
            }
        } catch (\Exception $e) {
            Logger::getInstance()->warning('Redis não disponível. Usando cache em memória.', [
                'error' => $e->getMessage()
            ]);
            $this->available = false;
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtém valor do cache
     */
    public function get(string $key)
    {
        if (!$this->available) {
            return null;
        }

        try {
            $value = $this->redis->get($key);
            if ($value === false) {
                return null;
            }
            return json_decode($value, true);
        } catch (\Exception $e) {
            Logger::getInstance()->warning('Erro ao obter do Redis', ['key' => $key]);
            return null;
        }
    }

    /**
     * Armazena valor no cache
     */
    public function set(string $key, $value, int $ttl = 3600): bool
    {
        if (!$this->available) {
            return false;
        }

        try {
            return $this->redis->setex($key, $ttl, json_encode($value));
        } catch (\Exception $e) {
            Logger::getInstance()->warning('Erro ao setar no Redis', ['key' => $key]);
            return false;
        }
    }

    /**
     * Incrementa valor (para rate limiting)
     */
    public function increment(string $key, int $value = 1, int $ttl = 60): int
    {
        if (!$this->available) {
            return 0;
        }

        try {
            $result = $this->redis->incrBy($key, $value);
            $this->redis->expire($key, $ttl);
            return (int)$result;
        } catch (\Exception $e) {
            Logger::getInstance()->warning('Erro ao incrementar Redis', ['key' => $key]);
            return 0;
        }
    }

    /**
     * Deleta chave
     */
    public function delete(string $key): bool
    {
        if (!$this->available) {
            return false;
        }

        try {
            return $this->redis->del($key) > 0;
        } catch (\Exception $e) {
            Logger::getInstance()->warning('Erro ao deletar Redis', ['key' => $key]);
            return false;
        }
    }

    /**
     * Limpa todas as chaves com padrão
     */
    public function flush(string $pattern = '*'): int
    {
        if (!$this->available) {
            return 0;
        }

        try {
            $keys = $this->redis->keys($pattern);
            if (empty($keys)) {
                return 0;
            }
            return $this->redis->del($keys);
        } catch (\Exception $e) {
            Logger::getInstance()->warning('Erro ao fazer flush Redis');
            return 0;
        }
    }

    /**
     * Verifica se está disponível
     */
    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * Obtém informações do Redis
     */
    public function info(): ?array
    {
        if (!$this->available) {
            return null;
        }

        try {
            return $this->redis->info();
        } catch (\Exception $e) {
            return null;
        }
    }
}
