<?php

namespace Core;

/**
 * Validador de tipos para propriedades de modelos
 */
class TypeValidator
{
    /**
     * Tipos permitidos e suas validações
     */
    private static array $validators = [
        'string' => 'is_string',
        'int' => 'is_int',
        'float' => 'is_float',
        'bool' => 'is_bool',
        'array' => 'is_array',
        'email' => 'filter_var',
        'url' => 'filter_var',
        'date' => 'isValidDate',
        'numeric' => 'is_numeric',
        'text' => 'is_string'
    ];

    /**
     * Valida valor contra tipo especificado
     */
    public static function validate($value, string $type): bool
    {
        if ($value === null) {
            return true; // Null é permitido
        }

        return match($type) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'date' => self::isValidDate($value),
            'int' => is_int($value) || (is_numeric($value) && (int)$value == $value),
            'float' => is_float($value) || is_numeric($value),
            'bool' => is_bool($value),
            'string' => is_string($value),
            'array' => is_array($value),
            'numeric' => is_numeric($value),
            'text' => is_string($value) && strlen($value) > 0,
            default => throw new \InvalidArgumentException("Tipo '{$type}' não suportado")
        };
    }

    /**
     * Converte valor para tipo especificado
     */
    public static function cast($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match($type) {
            'string' => (string)$value,
            'int' => (int)$value,
            'float' => (float)$value,
            'bool' => (bool)$value,
            'array' => (array)$value,
            default => $value
        };
    }

    /**
     * Valida formato de data (Y-m-d ou Y-m-d H:i:s)
     */
    private static function isValidDate($date): bool
    {
        if (!is_string($date)) {
            return false;
        }

        $formats = ['Y-m-d', 'Y-m-d H:i:s'];
        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sanitiza valor removendo caracteres perigosos
     */
    public static function sanitize($value, string $type = 'string')
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return array_map(fn($v) => self::sanitize($v, $type), $value);
        }

        return match($type) {
            'email' => filter_var($value, FILTER_SANITIZE_EMAIL),
            'url' => filter_var($value, FILTER_SANITIZE_URL),
            'string', 'text' => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
            'int' => (int)$value,
            'float' => (float)$value,
            default => $value
        };
    }
}
