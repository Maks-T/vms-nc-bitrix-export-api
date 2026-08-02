<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once __DIR__ . '/vendor/autoload.php';

use DI\Container;
use VmsNcApi\Bootstrap\ErrorHandler;
use VmsNcApi\Services\LogService;
use Slim\App;
use Slim\Psr7\Factory\ResponseFactory;

$appConfig = require __DIR__ . '/config/app.php';

ErrorHandler::register($appConfig['storage_path']);

// 1. Создаем DI Container
$container = new Container();

// 2. Инициализируем Slim App напрямую без вызова AppFactory (100% обход ParseError)
$responseFactory = new ResponseFactory();
$app = new App($responseFactory, $container);

$app->setBasePath($appConfig['app_path']);
$app->addBodyParsingMiddleware();

// 3. Загружаем роуты
$routes = require __DIR__ . '/routes.php';
$routes($app);

LogService::info('API request received: ' . $_SERVER['REQUEST_URI']);

// 4. Запускаем приложение
$app->run();