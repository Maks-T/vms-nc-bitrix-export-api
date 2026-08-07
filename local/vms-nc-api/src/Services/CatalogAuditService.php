<?php

declare(strict_types=1);

namespace VmsNcApi\Services;

use VmsNcApi\DTO\CatalogExportDTO;

final class CatalogAuditService
{
  public function shouldRunAudit(array $queryParams): bool
  {
    $isDebug = isset($queryParams['debug']) && in_array(strtolower((string)$queryParams['debug']), ['true', '1', 'yes'], true);

    return $isDebug && !empty($queryParams['method']);
  }

  public function run(CatalogExportDTO $catalogDto, array $queryParams, array $clientConfig = []): array
  {
    $method = strtolower((string)($queryParams['method'] ?? 'summary'));

    switch ($method) {
      case 'dims':
        return $this->auditRequiredEav($catalogDto, $clientConfig, 'dims');

      case 'meta':
        return $this->auditRequiredEav($catalogDto, $clientConfig, 'meta');

      case 'prices':
        return $this->auditMissingPrices($catalogDto);

      case 'multi':
        return $this->auditMultiVariants($catalogDto);

      case 'summary':
        return [
          'audit_mode' => 'full_summary',
          'missing_dimensions' => $this->auditRequiredEav($catalogDto, $clientConfig, 'dims'),
          'missing_meta' => $this->auditRequiredEav($catalogDto, $clientConfig, 'meta'),
          'missing_prices' => $this->auditMissingPrices($catalogDto),
          'multi_variants' => $this->auditMultiVariants($catalogDto),
        ];

      default:
        return [
          'status' => 'error',
          'message' => "Неизвестный метод аудита: '$method'. Доступные методы: 'dims', 'meta', 'prices', 'multi', 'summary'.",
          'allowed_methods' => ['dims', 'meta', 'prices', 'multi', 'summary']
        ];
    }
  }

  private function auditRequiredEav(CatalogExportDTO $catalogDto, array $clientConfig, string $auditKey): array
  {
    $auditConfig = $clientConfig['audits'][$auditKey] ?? [];
    $requiredEav = $auditConfig['required_eav'] ?? ($auditKey === 'dims' ? ['length', 'width', 'height'] : ['brand', 'collection']);
    $title = $auditConfig['name'] ?? 'Аудит обязательных характеристик';
    $targetScope = $auditConfig['scope'] ?? null;

    $incompleteItems = [];

    foreach ($catalogDto->products as $product) {
      $typeCode = (string)($product['product_type_external_code'] ?? '');

      if ($targetScope !== null && strpos($typeCode, $targetScope) === false) {
        continue;
      }

      $prodEav = $product['eav'] ?? [];
      $variants = $product['variants'] ?? [];

      foreach ($variants as $variant) {
        $varEav = $variant['eav'] ?? [];
        $combinedEav = array_merge($prodEav, $varEav);

        $missing = [];
        foreach ($requiredEav as $attrCode) {
          $val = $combinedEav[$attrCode] ?? null;

          if ($val === null || $val === '' || $val === 'opt_' || $val === 'opt_texture_' || $val === 'opt_brand_' || $val === 'opt_collection_') {
            $missing[] = $attrCode;
          }
        }

        if (!empty($missing)) {
          $logEntry = [
            'product_code' => $product['code'],
            'product_name' => $product['name']['ru'] ?? '',
            'sku' => $variant['sku'],
            'product_type' => $typeCode,
            'missing' => $missing,
            'current_eav' => $combinedEav,
          ];

          $incompleteItems[] = $logEntry;

          LogService::warning(
            "АУДИТ [$title]: Товар '{$product['code']}' (SKU: {$variant['sku']}) не имеет полей: " . implode(', ', $missing),
            $logEntry
          );
        }
      }
    }

    return [
      'audit_mode' => $auditKey,
      'title' => $title,
      'total_incomplete' => count($incompleteItems),
      'incomplete_items' => $incompleteItems,
    ];
  }

  private function auditMissingPrices(CatalogExportDTO $catalogDto): array
  {
    $invalidPrices = [];

    foreach ($catalogDto->products as $product) {
      $variants = $product['variants'] ?? [];

      foreach ($variants as $variant) {
        $costPrice = (float)($variant['cost_price'] ?? 0.0);
        $currency = trim((string)($variant['currency'] ?? ''));

        $issues = [];
        if ($costPrice <= 0.0) {
          $issues[] = 'cost_price (Нулевая себестоимость)';
        }
        if (empty($currency)) {
          $issues[] = 'currency (Отсутствует валюта)';
        }

        if (!empty($issues)) {
          $logEntry = [
            'product_code' => $product['code'],
            'product_name' => $product['name']['ru'] ?? '',
            'sku' => $variant['sku'],
            'cost_price' => $costPrice,
            'currency' => $currency,
            'issues' => $issues,
          ];

          $invalidPrices[] = $logEntry;

          LogService::warning(
            "АУДИТ ЦЕН: Товар '{$product['code']}' (SKU: {$variant['sku']}) имеет проблемы: " . implode(', ', $issues),
            $logEntry
          );
        }
      }
    }

    return [
      'audit_mode' => 'invalid_product_prices',
      'total_invalid_prices' => count($invalidPrices),
      'invalid_products' => $invalidPrices
    ];
  }

  private function auditMultiVariants(CatalogExportDTO $catalogDto): array
  {
    $products = $catalogDto->products;

    $multiVariantProducts = array_values(array_filter($products, function ($p) {
      $variants = $p['variants'] ?? [];
      return count($variants) > 1;
    }));

    return [
      'debug_mode' => 'multi_variants_only',
      'total_multi_variant_products' => count($multiVariantProducts),
      'products' => $multiVariantProducts
    ];
  }
}