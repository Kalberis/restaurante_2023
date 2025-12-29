<?php

namespace Core;

/**
 * Sistema de eventos para desacoplar código
 */
class EventDispatcher
{
    private static array $listeners = [];

    /**
     * Registra listener para um evento
     */
    public static function listen(string $event, callable $callback, int $priority = 0): void
    {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }

        self::$listeners[$event][] = [
            'callback' => $callback,
            'priority' => $priority
        ];

        // Ordena por prioridade (maior = executa primeiro)
        usort(self::$listeners[$event], fn($a, $b) => $b['priority'] <=> $a['priority']);
    }

    /**
     * Dispara um evento
     */
    public static function dispatch(string $event, array $data = []): void
    {
        if (!isset(self::$listeners[$event])) {
            return;
        }

        Logger::getInstance()->debug("Evento disparado", ['event' => $event, 'data' => $data]);

        foreach (self::$listeners[$event] as $listener) {
            call_user_func($listener['callback'], $data);
        }
    }

    /**
     * Remove listeners de um evento
     */
    public static function forget(string $event): void
    {
        unset(self::$listeners[$event]);
    }

    /**
     * Remove todos os listeners
     */
    public static function clear(): void
    {
        self::$listeners = [];
    }
}
