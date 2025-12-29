<?php

namespace Core;

/**
 * Gerenciador de tokens CSRF (Cross-Site Request Forgery)
 */
class CsrfToken
{
    private static $token_key = '__csrf_token__';
    private static $field_name = '_csrf_token';

    /**
     * Gera ou retorna token CSRF existente
     */
    public static function generate(): string
    {
        $session = Session::getInstance();
        
        if (!isset($session->{self::$token_key})) {
            $session->{self::$token_key} = bin2hex(random_bytes(32));
        }
        
        return $session->{self::$token_key};
    }

    /**
     * Valida o token CSRF recebido
     */
    public static function validate(string $token): bool
    {
        $session = Session::getInstance();
        $expected = isset($session->{self::$token_key}) ? $session->{self::$token_key} : null;
        
        if ($expected === null) {
            return false;
        }

        // Usar hash_equals para evitar timing attacks
        return hash_equals($expected, $token);
    }

    /**
     * Retorna o nome do campo HTML para o token
     */
    public static function getFieldName(): string
    {
        return self::$field_name;
    }

    /**
     * Regenera um novo token (útil após login)
     */
    public static function regenerate(): string
    {
        $session = Session::getInstance();
        unset($session->{self::$token_key});
        return self::generate();
    }

    /**
     * Retorna HTML hidden input para forms
     */
    public static function getInput(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars(self::$field_name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(self::generate(), ENT_QUOTES, 'UTF-8')
        );
    }
}
