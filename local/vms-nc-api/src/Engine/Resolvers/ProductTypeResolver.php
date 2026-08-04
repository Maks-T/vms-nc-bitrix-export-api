<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Resolvers;

final class ProductTypeResolver
{
  /**
   * Динамически определяет тип товара на основе правил из конфига клиента
   *
   * @param array $productData Массив полей товара Битрикс
   * @param int $sectionId ID категории товара
   * @param array $rulesConfig Правила из конфига клиента
   * @return string (например 'type_acrylic_stone', 'type_quartz_stone', 'type_kitchen_sink')
   */
  public static function resolve(array $productData, int $sectionId, array $rulesConfig): string
  {
    $defaultType = (string)($rulesConfig['default'] ?? 'type_acrylic_stone');
    $rules       = $rulesConfig['rules'] ?? [];

    if (empty($rules)) {
      return $defaultType;
    }

    foreach ($rules as $rule) {
      $targetType = (string)($rule['type'] ?? '');
      $condition  = (string)($rule['condition'] ?? '');

      if ($targetType === '') {
        continue;
      }

      if ($condition === 'section_id') {
        $allowedSectionIds = array_map('intval', (array)($rule['values'] ?? []));
        if (in_array($sectionId, $allowedSectionIds, true)) {
          return $targetType;
        }
      }

      if ($condition === 'regex') {
        $field = (string)($rule['field'] ?? 'NAME');
        $valueToCheck = (string)($productData[$field] ?? '');
        $pattern = (string)($rule['pattern'] ?? '');

        if ($pattern !== '' && preg_match($pattern, $valueToCheck)) {
          return $targetType;
        }
      }
    }

    return $defaultType;
  }

}