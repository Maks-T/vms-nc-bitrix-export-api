<?php

declare(strict_types=1);

namespace VmsNcApi\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;
use VmsNcApi\Engine\CatalogExportEngine;
use VmsNcApi\Services\CatalogAuditService;

final class ExportController
{
  private CatalogExportEngine $exportEngine;
  private CatalogAuditService $auditService;

  public function __construct(
    CatalogExportEngine $exportEngine,
    CatalogAuditService $auditService
  ) {
    $this->exportEngine = $exportEngine;
    $this->auditService = $auditService;
  }

  public function exportCatalog(Request $request, Response $response, array $args): Response
  {
    $clientCode  = (string)($args['client'] ?? 'stoleshka_ru');
    $queryParams = $request->getQueryParams();

    // Выполняем обычную выгрузку DTO
    $catalogDto = $this->exportEngine->export($clientCode, $queryParams);

    // Если передан параметр аудита (?audit_dims=1 или ?debug_multi=1)
    if ($this->auditService->shouldRunAudit($queryParams)) {
      $auditPayload = $this->auditService->run($catalogDto, $queryParams);

      $payload = json_encode(
        $auditPayload,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
      );

      $response->getBody()->write($payload);
      return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    // В обычном режиме отдаем чистый каталог
    $responsePayload = $catalogDto->toArray();

    // Мета-информация об отфильтрованном запросе (если использовалась пагинация или поиск)
    if (!empty($queryParams['product_type']) || !empty($queryParams['type']) || !empty($queryParams['code']) || !empty($queryParams['page'])) {
      $responsePayload['filter_meta'] = [
        'product_type' => $queryParams['product_type'] ?? ($queryParams['type'] ?? null),
        'code'         => $queryParams['code'] ?? null,
        'page'         => isset($queryParams['page']) ? (int)$queryParams['page'] : null,
        'limit'        => isset($queryParams['limit']) ? (int)$queryParams['limit'] : null,
        'total_count'  => count($responsePayload['products'] ?? []),
      ];
    }

    $payload = json_encode(
      $responsePayload,
      JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
  }
}