<?php

declare(strict_types=1);

namespace VmsNcApi\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;
use VmsNcApi\Engine\CatalogExportEngine;

final class ExportController
{
  private CatalogExportEngine $exportEngine;

  public function __construct(CatalogExportEngine $exportEngine)
  {
    $this->exportEngine = $exportEngine;
  }

  /**
   * GET /export/{client}
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   * @return Response
   * @throws Throwable
   * @noinspection PhpUnused
   * @noinspection PhpUnusedParameterInspection
   * @noinspection SpellCheckingInspection
   */
  public function exportCatalog(Request $request, Response $response, array $args): Response
  {
    $clientCode = (string)($args['client'] ?? 'stoleshka_ru');

    $catalogDto = $this->exportEngine->export($clientCode);

    $payload = json_encode(
      $catalogDto->toArray(),
      JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
  }
}