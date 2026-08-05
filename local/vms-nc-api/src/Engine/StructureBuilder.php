<?php

declare(strict_types=1);

namespace VmsNcApi\Engine;

use VmsNcApi\Engine\Resolvers\VariantPriorityResolver;

final class StructureBuilder
{
  public static function build(array $flatProducts, array $clientConfig = []): array
  {
    $indexed = [];
    foreach ($flatProducts as $item) {
      if (!$item['is_variant'] && !isset($item['variants'])) {
        $item['variants'] = [];
      }
      $indexed[$item['code']] = $item;
    }

    foreach ($indexed as $item) {
      if ($item['is_variant'] && !empty($item['parent_code'])) {
        $parentCode = $item['parent_code'];
        if (isset($indexed[$parentCode])) {
          $indexed[$parentCode]['variants'][] = $item['variant_data'];
        }
      }
    }

    $result = [];
    $priorityRules = $clientConfig['variant_priority_rules'] ?? [];

    foreach ($indexed as $item) {
      if (!$item['is_variant']) {

        if (!empty($item['variants']) && !empty($priorityRules)) {
          $productTypeCode = (string)($item['product_type_external_code'] ?? '');
          $item['variants'] = VariantPriorityResolver::resolve(
            $item['variants'],
            $productTypeCode,
            $priorityRules
          );
        }

        if (empty($item['variants'])) {
          $defaultPrice = $item['default_price_data'] ?? ['price' => 0.0, 'currency' => 'USD'];
          $defSkuCode = $item['code'] . '-def';

          $variantEav = [
            'cutting_groups' => 'rec_cutting_groups_2'
          ];

          foreach (['length', 'width', 'height'] as $dimAttr) {
            if (isset($item['eav'][$dimAttr])) {
              $variantEav[$dimAttr] = $item['eav'][$dimAttr];
            }
          }

          $item['variants'][] = [
            'external_code' => 'sku_' . $defSkuCode,
            'sku' => $defSkuCode,
            'name' => null,
            'price_group_external_code' => null,
            'stock' => 10,
            'is_default' => true,
            'preview_picture' => $item['preview_picture'],
            'detail_picture' => null, //$item['detail_picture'],
            'eav' => $variantEav,
            'is_manual_pricing' => true,
            'cost_price' => $defaultPrice['price'],
            'currency' => $defaultPrice['currency']
          ];
        }

        unset($item['is_variant'], $item['parent_code'], $item['variant_data'], $item['default_price_data']);
        $result[] = $item;
      }
    }

    return $result;
  }
}