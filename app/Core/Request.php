<?php

namespace Core;

class Request{
    private static $instance;

    private function __construct(){
        Session::getInstance();
    }

    public static function getInstance(): self
    {
        if(is_null(self::$instance)){
            self::$instance = new Request;
        }
        return self::$instance;
    }

    public function getUrl(): string
    {
        $url = $this->url ?? null;
        return (isset($url)) ? $url : '/';
    }

    /**
     * Obtém valor do POST com sanitização
     */
    public function post(string $name, $default = null)
    {
        return array_key_exists($name, $_POST) 
            ? $this->sanitize($_POST[$name])
            : $default;
    }

    /**
     * Obtém valor do GET com sanitização
     */
    public function get(string $name, $default = null)
    {
        return array_key_exists($name, $_GET)
            ? $this->sanitize($_GET[$name])
            : $default;
    }

    /**
     * Obtém valor de REQUEST (compatibilidade) - USAR POST/GET quando possível
     */
    public function __get(string $name)
    {
        if (array_key_exists($name, $_POST)) {
            return $this->sanitize($_POST[$name]);
        }
        if (array_key_exists($name, $_GET)) {
            return $this->sanitize($_GET[$name]);
        }
        return null;
    }

    public function __isset(string $name): bool
    {
        return (array_key_exists($name, $_REQUEST));
    }

    /**
     * Sanitiza valor removendo tags HTML e espaços
     */
    private function sanitize($value)
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitize'], $value);
        }
        
        if (is_string($value)) {
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
        
        return $value;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function getAction()
    {
        return Action::createActionByUrl($this->getUrl(), $this->getMethod());
    }

    /**
     * Valida token CSRF para POST requests
     */
    public function validateCsrf(): bool
    {
        if ($this->getMethod() !== 'POST') {
            return true;
        }

        $token = $this->post(CsrfToken::getFieldName());
        if (!$token) {
            throw new \Exception('Token CSRF ausente', 403);
        }

        return CsrfToken::validate($token);
    }

}