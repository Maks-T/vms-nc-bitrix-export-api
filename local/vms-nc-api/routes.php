<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use VmsNcApi\Controllers\ExportController;
use VmsNcApi\Controllers\ImageExportController;

return function (App $app) {

  /*
  |--------------------------------------------------------------------------
  | Проверка работоспособности системы (Healthcheck)
  |--------------------------------------------------------------------------
  */
  $app->get('/', function (Request $request, Response $response) {
    $data = [
      'status'    => 'success',
      'message'   => 'VMS-NC Integration API is running!',
      'timestamp' => date('c'),
    ];

    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    return $response->withHeader('Content-Type', 'application/json');
  });

  /*
  |--------------------------------------------------------------------------
  | Экспорт каталога товаров VMS-NC
  |--------------------------------------------------------------------------
  */
  $app->get('/export/{client}', [ExportController::class, 'exportCatalog']);

  /*
  |--------------------------------------------------------------------------
  | Сборка и выгрузка медиафайлов (Изображения)
  |--------------------------------------------------------------------------
  */
  $app->get('/export/{client}/images', [ImageExportController::class, 'exportImages']);
  $app->get('/export/{client}/images/zip', [ImageExportController::class, 'downloadZip']);

};