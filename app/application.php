<?php

/**
 * Responsável pela inicialização do Sistema
 */

date_default_timezone_set("America/Araguaina");

// Registra manipulador de erros e exceções
require_once('Core/Logger.php');
require_once('Core/ErrorHandler.php');
\Core\ErrorHandler::register();

require_once('Configs/framework.php');
require_once(COMPOSER_PATH . '/autoload.php');
\Core\Configs::createConfigsDB();
require_once('Configs/app.php');
require_once('Core/helpers.php');
require_once('Configs/routers.php');
