<?php

declare(strict_types=1);

namespace VmsNcApi\Engine;

final class StructureBuilder
{
  public static function build(array $flatProducts): array
  {
    $indexed = [];
    foreach ($flatProducts as $item) {
      $indexed[$item['code']] = $item;
    }

    // Привязываем предложения (SKU) к родителям по parent_code
    foreach ($indexed as $code => $item) {
      if ($item['is_variant'] && !empty($item['parent_code'])) {
        $parentCode = $item['parent_code'];
        if (isset($indexed[$parentCode])) {
          // Переносим вариант внутрь родителя
          $indexed[$parentCode]['variants'][] = $item['variant_data'];
        }
      }
    }

    // Оставляем только базовые товары
    $result = [];
    foreach ($indexed as $code => $item) {
      if (!$item['is_variant']) {
        unset($item['is_variant'], $item['parent_code'], $item['variant_data']);
        $result[] = $item;
      }
    }

    return $result;
  }
}