<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once __DIR__ . '/vendor/autoload.php';

use DI\Container;
use VmsNcApi\Bootstrap\ErrorHandler;
use VmsNcApi\Services\LogService;
use Slim\Factory\AppFactory;

$appConfig = require __DIR__ . '/config/app.php';

ErrorHandler::register($appConfig['storage_path']);

$container = new Container();
AppFactory::setContainer($container);

$app = AppFactory::create();
$app->setBasePath($appConfig['app_path']);

$app->addBodyParsingMiddleware();

$routes = require __DIR__ . '/routes.php';
$routes($app);

LogService::info('API request received: ' . $_SERVER['REQUEST_URI']);
$app->run();