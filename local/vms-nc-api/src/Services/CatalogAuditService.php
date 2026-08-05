<?php

declare(strict_types=1);

namespace VmsNcApi\Services;

use VmsNcApi\DTO\CatalogExportDTO;

final class CatalogAuditService
{
  /**
   * Проверяет, активирован ли режим отладки/аудита (debug=true)
   */
  public function shouldRunAudit(array $queryParams): bool
  {
    $isDebug = isset($queryParams['debug']) && in_array(strtolower((string)$queryParams['debug']), ['true', '1', 'yes'], true);

    return $isDebug && !empty($queryParams['method']);
  }

  /**
   * Запускает выбранный метод отладки/аудита
   */
  public function run(CatalogExportDTO $catalogDto, array $queryParams): array
  {
    $method = strtolower((string)($queryParams['method'] ?? ''));

    switch ($method) {
      case 'dims':
      case 'missing_dims':
      case 'audit_dims':
        return $this->auditMissingDimensions($catalogDto);

      case 'multi':
      case 'multi_variants':
        return $this->auditMultiVariants($catalogDto);

      case 'all':
      case 'summary':
        return [
          'audit_mode'         => 'full_summary',
          'missing_dimensions' => $this->auditMissingDimensions($catalogDto),
          'multi_variants'     => $this->auditMultiVariants($catalogDto),
        ];

      default:
        return [
          'status'          => 'error',
          'message'         => "Неизвестный метод аудита: '$method'. Доступные методы: 'dims', 'multi', 'all'.",
          'allowed_methods' => ['dims', 'multi', 'all']
        ];
    }
  }

  /**
   * Метод: dims (аудит полноты размеров для каменных товаров)
   */
  private function auditMissingDimensions(CatalogExportDTO $catalogDto): array
  {
    $incompleteStones = [];

    foreach ($catalogDto->products as $product) {
      $typeCode = (string)($product['product_type_external_code'] ?? '');

      if (strpos($typeCode, 'stone') !== false) {
        $prodEav  = $product['eav'] ?? [];
        $variants = $product['variants'] ?? [];

        foreach ($variants as $variant) {
          $varEav = $variant['eav'] ?? [];

          $length = $varEav['length'] ?? ($prodEav['length'] ?? null);
          $width  = $varEav['width']  ?? ($prodEav['width']  ?? null);
          $height = $varEav['height'] ?? ($prodEav['height'] ?? null);

          $missing = [];
          if (empty($length) || (int)$length <= 0) {
            $missing[] = 'length (Длина)';
          }
          if (empty($width) || (int)$width <= 0) {
            $missing[] = 'width (Ширина)';
          }
          if (empty($height) || (int)$height <= 0) {
            $missing[] = 'height (Толщина)';
          }

          if (!empty($missing)) {
            $logEntry = [
              'product_code'       => $product['code'],
              'product_name'       => $product['name']['ru'] ?? '',
              'sku'                => $variant['sku'],
              'product_type'       => $typeCode,
              'missing_dimensions' => $missing,
              'current_eav'        => array_merge($prodEav, $varEav)
            ];

            $incompleteStones[] = $logEntry;

            LogService::warning(
              "АУДИТ РАЗМЕРОВ: Камень '{$product['code']}' (SKU: {$variant['sku']}) не имеет полей: " . implode(', ', $missing),
              $logEntry
            );
          }
        }
      }
    }

    return [
      'audit_mode'              => 'incomplete_stone_dimensions',
      'total_incomplete_stones' => count($incompleteStones),
      'incomplete_products'     => $incompleteStones
    ];
  }

  /**
   * Метод: multi (дебаг товаров с несколькими вариациями)
   */
  private function auditMultiVariants(CatalogExportDTO $catalogDto): array
  {
    $products = $catalogDto->products;

    $multiVariantProducts = array_values(array_filter($products, function ($p) {
      $variants = $p['variants'] ?? [];
      return count($variants) > 1;
    }));

    return [
      'debug_mode'                   => 'multi_variants_only',
      'total_multi_variant_products' => count($multiVariantProducts),
      'products'                     => $multiVariantProducts
    ];
  }
}