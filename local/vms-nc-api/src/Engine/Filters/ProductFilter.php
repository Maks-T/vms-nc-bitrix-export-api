<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Filters;

use VmsNcApi\Engine\Conditions\ConditionEvaluator;

final class ProductFilter
{
  public static function isProductAllowed(
    array $product,
    int $sectionId,
    string $productTypeExternalCode,
    array $productEavValues,
    array $clientConfig
  ): bool {
    // Проверка разделов
    if ($sectionId > 0 && !CategoryFilter::isSectionAllowed($sectionId, $clientConfig['category_filters'] ?? [])) {
      return false;
    }

    // Единая проверка правил исключения через ConditionEvaluator
    $rules = $clientConfig['product_filters']['rules'] ?? [];
    $context = [
      'product'           => $product,
      'product_eav'       => $productEavValues,
      'product_type'      => $productTypeExternalCode,
      'bitrix_element'    => $product,
      'bitrix_properties' => $productEavValues,
    ];

    foreach ($rules as $rule) {
      if (ConditionEvaluator::matches($rule, $context)) {
        return false; // Если условие правила совпало — исключаем товар!
      }
    }

    return true;
  }
}