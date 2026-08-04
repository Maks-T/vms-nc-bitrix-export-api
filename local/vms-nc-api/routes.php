<?php

declare(strict_types=1);

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use VmsNcApi\Controllers\ExportController;

return function (App $app) {

  // Healthcheck
  $app->get('/', function (Request $request, Response $response) {
    $data = [
      'status'    => 'success',
      'message'   => 'VMS-NC Integration API is running!',
      'timestamp' => date('c')
    ];
    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
  });

  // Эндпоинт экспорта каталога клиента
  $app->get('/export/{client}', ExportController::class . ':exportCatalog');

};