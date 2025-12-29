<?php
/**
 * Bootstrap para testes PHPUnit
 */

// Define constantes necessárias
define('BASE_PATH', dirname(__DIR__));
define('COMPOSER_PATH', BASE_PATH . '/vendor');
define('APPLICATION_ENV', 'testing');
define('TEMPLATES_PATH', BASE_PATH . '/app/Templates');
define('TEMPLATE_DEFAULT', 'main');
define('SESSION_NAME', 'restaurante_session');

// Carrega autoloader do composer
require COMPOSER_PATH . '/autoload.php';

// Carrega aplicação base
require BASE_PATH . '/app/application.php';
