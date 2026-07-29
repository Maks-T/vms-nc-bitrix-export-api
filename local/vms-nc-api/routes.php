<?php

declare(strict_types=1);

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app) {

  // Тестовый эндпоинт проверки работоспособности (Healthcheck)
  $app->get('/', function (Request $request, Response $response) {
    $data = [
      'status'  => 'success',
      'message' => 'VMS-NC Integration API v3 is running!',
      'timestamp' => date('c')
    ];
    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
  });

};