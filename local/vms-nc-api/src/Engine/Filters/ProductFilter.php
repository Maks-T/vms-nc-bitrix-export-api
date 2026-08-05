<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Filters;

final class ProductFilter
{
  /**
   * Комплексная проверка разрешен ли товар к выгрузке
   *
   * @param array $product Поля элемента Битрикс
   * @param int $sectionId ID раздела
   * @param string $productTypeExternalCode Рассчитанный тип товара
   * @param array $productEavValues EAV свойства товара
   * @param array $clientConfig Конфиг клиента
   * @return bool
   */
  public static function isProductAllowed(
    array $product,
    int $sectionId,
    string $productTypeExternalCode,
    array $productEavValues,
    array $clientConfig
  ): bool {
    // 1. Проверка по разделам (CategoryFilter)
    $categoryConfig = $clientConfig['category_filters'] ?? [];
    if ($sectionId > 0 && !CategoryFilter::isSectionAllowed($sectionId, $categoryConfig)) {
      return false;
    }

    // 2. Проверка по EAV правилам (SNYAT, PRODANO и т.д.)
    $productFilters = $clientConfig['product_filters'] ?? [];
    if (!self::isEavAllowed($productEavValues, $productFilters)) {
      return false;
    }

    // 3. Проверка по query-параметрам URL (?code=... & ?product_type=...)
    $queryParams = $clientConfig['query_params'] ?? [];
    $filterType  = $queryParams['product_type'] ?? ($queryParams['type'] ?? null);
    $filterCode  = !empty($queryParams['code']) ? strtolower((string)$queryParams['code']) : null;

    $code = !empty($product['CODE']) ? strtolower((string)$product['CODE']) : 'prod-' . ($product['ID'] ?? 0);

    if ($filterCode !== null && $code !== $filterCode) {
      return false;
    }

    if ($filterType !== null && $productTypeExternalCode !== $filterType) {
      return false;
    }

    return true;
  }

  /**
   * Проверка EAV-исключений (SNYAT, PRODANO и т.д.)
   */
  private static function isEavAllowed(array $productEavValues, array $productFiltersConfig): bool
  {
    $rules = $productFiltersConfig['rules'] ?? [];

    if (empty($rules)) {
      return true;
    }

    foreach ($rules as $rule) {
      $propertyCode = $rule['property'] ?? '';
      $operator     = $rule['operator'] ?? 'not_empty';
      $value        = $productEavValues[$propertyCode] ?? null;

      if ($operator === 'not_empty' || $operator === 'truthy') {
        if (!empty($value)) {
          return false;
        }
      }

      if ($operator === 'equals' || $operator === '==') {
        $targetValue = $rule['value'] ?? null;
        if ($value === $targetValue) {
          return false;
        }
      }
    }

    return true;
  }
}