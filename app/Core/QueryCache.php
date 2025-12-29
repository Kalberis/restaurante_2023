<?php

namespace Core;

/**
 * Sistema de cache simples para queries SELECT
 * Suporta TTL (Time To Live) configurável
 */
class QueryCache
{
    private static array $cache = [];
    private static int $default_ttl = 3600; // 1 hora
    private static bool $enabled = true;

    /**
     * Habilita/desabilita cache globalmente
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    /**
     * Define TTL padrão em segundos
     */
    public static function setDefaultTTL(int $seconds): void
    {
        self::$default_ttl = max(1, $seconds);
    }

    /**
     * Gera chave de cache a partir da query
     */
    private static function generateKey(string $sql, array $params): string
    {
        return 'query_' . hash('sha256', $sql . json_encode($params));
    }

    /**
     * Obtém resultado do cache se existir e for válido
     */
    public static function get(string $sql, array $params)
    {
        if (!self::$enabled) {
            return null;
        }

        $key = self::generateKey($sql, $params);

        if (!isset(self::$cache[$key])) {
            return null;
        }

        $cached = self::$cache[$key];

        // Verifica se ainda está válido
        if (time() > $cached['expires_at']) {
            unset(self::$cache[$key]);
            return null;
        }

        Logger::getInstance()->debug("Cache HIT para query", ['key' => $key]);
        return $cached['data'];
    }

    /**
     * Armazena resultado em cache
     */
    public static function set(string $sql, array $params, $data, int $ttl = null): void
    {
        if (!self::$enabled) {
            return;
        }

        $key = self::generateKey($sql, $params);
        $ttl = $ttl ?? self::$default_ttl;

        self::$cache[$key] = [
            'data' => $data,
            'expires_at' => time() + $ttl,
            'created_at' => time()
        ];

        Logger::getInstance()->debug("Cache SET para query", ['key' => $key, 'ttl' => $ttl]);
    }

    /**
     * Limpa cache de uma query específica
     */
    public static function forget(string $sql, array $params = []): void
    {
        $key = self::generateKey($sql, $params);
        unset(self::$cache[$key]);
        Logger::getInstance()->debug("Cache FORGET", ['key' => $key]);
    }

    /**
     * Limpa cache com padrão (wildcards)
     */
    public static function forgetPattern(string $pattern): int
    {
        $count = 0;
        foreach (array_keys(self::$cache) as $key) {
            if (fnmatch($pattern, $key)) {
                unset(self::$cache[$key]);
                $count++;
            }
        }
        Logger::getInstance()->debug("Cache FORGET PATTERN", ['pattern' => $pattern, 'cleared' => $count]);
        return $count;
    }

    /**
     * Limpa todo o cache
     */
    public static function flush(): void
    {
        $count = count(self::$cache);
        self::$cache = [];
        Logger::getInstance()->info("Cache FLUSH", ['items' => $count]);
    }

    /**
     * Retorna estatísticas do cache
     */
    public static function getStats(): array
    {
        $total = count(self::$cache);
        $expired = 0;

        foreach (self::$cache as $item) {
            if (time() > $item['expires_at']) {
                $expired++;
            }
        }

        return [
            'total' => $total,
            'expired' => $expired,
            'valid' => $total - $expired,
            'enabled' => self::$enabled
        ];
    }

    /**
     * Limpa items expirados
     */
    public static function cleanup(): int
    {
        $removed = 0;
        $now = time();

        foreach (array_keys(self::$cache) as $key) {
            if ($now > self::$cache[$key]['expires_at']) {
                unset(self::$cache[$key]);
                $removed++;
            }
        }

        if ($removed > 0) {
            Logger::getInstance()->debug("Cache CLEANUP", ['removed' => $removed]);
        }

        return $removed;
    }
}
