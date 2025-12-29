<?php

namespace Core;

/**
 * Gerencia todas as rotas do meu sistema.
 */
class Router
{
    /**
     * Armazena todas as requisições GET do sistema
     */
    private static array $get = [];
    private static array $post = [];
    private string $url;
    private string $controller;
    private string $action;
    private string $method;
    private array $params = [];
    private array $middlewares = [];

    private function __construct(string $url, string $controller, string $action, string $method)
    {
        $this->url = (substr($url, 0, 1) === '/') ? $url : "/{$url}";
        $this->controller = $controller;
        $this->action = $action;
        $this->method = $method;
        $this->params = array_fill_keys($this->getUrlParameters(), null);
        
        if ($method === "GET") {
            self::$get[$url] = $this;
        } else {
            self::$post[$url] = $this;
        }
    }

    /**
     * Extrai parâmetros dinâmicos da URL
     */
    public function getUrlParameters(): array
    {
        $expression = "(\{[a-z0-9_]{1,}\})";
        if(preg_match_all($expression, $this->url, $matches)){
            return preg_replace("(\{|\})", "", $matches[0]);
        }
        return [];
    }

    public static function get(string $url, string $controller, string $action = 'index'): self
    {
        return new Router($url, $controller, $action, 'GET');
    }

    public static function post(string $url, string $controller, string $action = 'index'): self
    {
        return new Router($url, $controller, $action, 'POST');
    }

    /**
     * Encontra rota pela URL com proteção contra injection
     */
    public static function getRouterByUrl(string $url, string $method = "GET"): ?Router
    {
        $url = (substr($url, 0, 1) === '/') ? $url : "/{$url}";
        $routers = ($method === "GET") ? self::$get : self::$post;
       
        foreach($routers as $router){
            // Padrão mais restritivo para evitar injections
            // Permite: letras, números, underscore, hífen, espaços, acentos
            $expression = preg_replace(
                "(\{[a-z0-9_]{1,}\})",
                "([a-zA-Z0-9_\\-áàâãéèêíïóôõöúçñÁÀÂÃÉÈÍÏÓÔÕÖÚÇÑüÜ ]+)",
                $router->url
            );

            if(preg_match("#^({$expression})$#i", $url, $matches) === 1){
                array_shift($matches);
                array_shift($matches);
                
                foreach($router->params as &$param){
                    $param = array_shift($matches);
                }
                return $router;
            }
        }
        return null;
    }

    /**
     * Encontra rota pelo controller e ação
     */
    public static function getRouterByController(
        string $controller, 
        string $action = 'index',
        string $method = 'GET',
        array $parameters = []
    ): ?Router
    {
        $routers = ($method === "GET") ? self::$get : self::$post;
        foreach($routers as $router){
            if($router->controller === $controller && $router->action === $action){
                if(count($router->params) === count($parameters)){
                    foreach($router->params as &$param){
                        $param = array_shift($parameters);
                    }
                    return $router;
                }
            }
        }
        return null;
    }

    public function getController(): string
    {
        return $this->controller;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getParameters(): array
    {
        return $this->params;
    }

    public function getUrl(): string
    {
        $url = $this->url;
        foreach($this->params as $var => $param){
            $url = str_replace("{{{$var}}}", urlencode((string)$param), $url);
        }
        return $url;
    }

    public function addMiddleware($middleware): self
    {
        if(!is_array($middleware)){
            $middleware = [$middleware];
        }
        $this->middlewares = array_merge($this->middlewares, $middleware);
        return $this;
    }

    public function checkMiddlewares(): bool
    {
        return Middleware::checkMiddlewares($this->middlewares);
    }

    public function execMiddlewares(): void
    {
        Middleware::execMiddlewares($this->middlewares);
    }
}