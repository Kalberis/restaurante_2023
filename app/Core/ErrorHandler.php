<?php

namespace Core;

/**
 * Gerenciador centralizado de erros e exceções
 */
class ErrorHandler
{
    private static $logger;

    public static function register(): void
    {
        self::$logger = Logger::getInstance();

        // Registra handler para exceções
        set_exception_handler([self::class, 'handleException']);

        // Registra handler para erros
        set_error_handler([self::class, 'handleError']);

        // Registra handler para shutdown (fatal errors)
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Trata exceções não capturadas
     */
    public static function handleException(\Throwable $exception): void
    {
        self::$logger->error('Exceção não tratada: ' . $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'trace' => $exception->getTraceAsString()
        ]);

        if (APPLICATION_ENV === 'production') {
            http_response_code(500);
            die('Erro interno do servidor. Por favor, tente novamente mais tarde.');
        } else {
            http_response_code($exception->getCode() ?: 500);
            echo '<pre>';
            echo 'Exceção: ' . htmlspecialchars($exception->getMessage()) . "\n";
            echo 'Arquivo: ' . htmlspecialchars($exception->getFile()) . ':' . $exception->getLine() . "\n";
            echo 'Trace: ' . htmlspecialchars($exception->getTraceAsString());
            echo '</pre>';
        }
        exit(1);
    }

    /**
     * Converte erros em exceções
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        self::$logger->warning('Erro PHP: ' . $errstr, [
            'file' => $errfile,
            'line' => $errline,
            'errno' => $errno
        ]);

        // Não converte avisos em exceções (E_WARNING, E_NOTICE, etc)
        if ($errno === E_ERROR || $errno === E_PARSE || $errno === E_CORE_ERROR) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        }

        return false;
    }

    /**
     * Trata fatal errors no shutdown
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::handleException(
                new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                )
            );
        }
    }
}
