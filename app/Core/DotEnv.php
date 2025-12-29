<?php

namespace Core;

/**
 * Carregador de variáveis de ambiente via arquivos .env
 */
class DotEnv
{
    private string $path;
    private array $values = [];

    public function __construct(string $base_path, string $app_env = 'development')
    {
        $this->path = $base_path;
        
        // Carrega arquivos em ordem (último tem prioridade)
        $files = [
            '.env',                    // Base
            '.env.local',              // Local (não commitar)
            ".env.{$app_env}",         // Específico do ambiente
            ".env.{$app_env}.local"    // Local específico do ambiente
        ];

        foreach ($files as $file) {
            $filepath = $base_path . DIRECTORY_SEPARATOR . $file;
            if (file_exists($filepath)) {
                $this->load($filepath);
            }
        }
    }

    /**
     * Carrega um arquivo .env
     */
    private function load(string $filepath): void
    {
        if (!is_readable($filepath)) {
            Logger::getInstance()->warning("Arquivo .env não legível", ['file' => $filepath]);
            return;
        }

        $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignora comentários
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Parse da linha
            if (strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove aspas
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            // Substitui variáveis de ambiente
            $value = $this->parseValue($value);

            $this->values[$key] = $value;
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }

    /**
     * Parse de valores com expansão de variáveis
     */
    private function parseValue(string $value): string
    {
        // Substitui ${VAR} ou $VAR por valores
        return preg_replace_callback('/\$\{([A-Z_][A-Z0-9_]*)\}|\$([A-Z_][A-Z0-9_]*)/', function($matches) {
            $var = $matches[1] ?? $matches[2];
            return $this->values[$var] ?? $_ENV[$var] ?? '';
        }, $value);
    }

    /**
     * Obtém valor da variável
     */
    public function get(string $key, $default = null)
    {
        return $this->values[$key] ?? $_ENV[$key] ?? $default;
    }

    /**
     * Define valor
     */
    public function set(string $key, $value): void
    {
        $this->values[$key] = $value;
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }

    /**
     * Verifica se existe
     */
    public function has(string $key): bool
    {
        return isset($this->values[$key]) || isset($_ENV[$key]);
    }

    /**
     * Retorna todos os valores
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * Helper static para uso rápido
     */
    private static ?self $instance = null;

    public static function init(string $path, string $app_env = 'development'): self
    {
        if (self::$instance === null) {
            self::$instance = new self($path, $app_env);
        }
        return self::$instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('DotEnv não foi inicializado. Chame DotEnv::init() primeiro.');
        }
        return self::$instance;
    }

    public static function env(string $key, $default = null)
    {
        return self::getInstance()->get($key, $default);
    }
}
