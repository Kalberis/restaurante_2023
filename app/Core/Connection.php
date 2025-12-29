<?php

namespace Core;

use PDO;
use PDOException;

class Connection
{
    private static ?PDO $connection = null;

    private function __construct(){}

    /**
     * Obtém instância singleton da conexão com banco de dados
     */
    public static function getInstance(): PDO
    {
        if (!isset(self::$connection)) {
            $database = Configs::getConfig("database");
            
            $dns = sprintf(
                "%s:host=%s;port=%s;dbname=%s",
                $database['driver'],
                $database['host'],
                $database['port'],
                $database['database']
            );

            $parameters = self::getDriverParameters($database['driver']);
            
            try {
                self::$connection = new PDO(
                    $dns,
                    $database['user'],
                    $database['password'],
                    $parameters
                );
                
                // Ativa exceções em caso de erro
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
            } catch (PDOException $e) {
                Logger::getInstance()->critical('Erro de conexão com banco de dados', [
                    'driver' => $database['driver'],
                    'host' => $database['host'],
                    'database' => $database['database'],
                    'error' => $e->getMessage()
                ]);

                if(APPLICATION_ENV === 'production'){
                    die('Erro de conexão com banco de dados');
                }
                throw $e;
            }
        }
        return self::$connection;
    }

    /**
     * Obtém parâmetros específicos do driver
     */
    private static function getDriverParameters(string $driver): array
    {
        return match($driver) {
            'mysql' => [
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
            ],
            'pgsql' => [
                PDO::ATTR_PERSISTENT => false
            ],
            'sqlite' => [
                PDO::ATTR_PERSISTENT => false
            ],
            default => []
        };
    }

    /**
     * Testa conexão com o banco
     */
    public static function test(): bool
    {
        try {
            self::getInstance()->getAttribute(PDO::ATTR_CONNECTION_STATUS);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}