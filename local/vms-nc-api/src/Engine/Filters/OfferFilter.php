<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Filters;

final class OfferFilter
{
  /**
   * Проверяет, подходит ли торговое предложение под правила фильтрации клиента
   */
  public static function isOfferAllowed(array $offerData, array $offerFiltersConfig): bool
  {
    $mode = $offerFiltersConfig['mode'] ?? 'whitelist';
    $rules = $offerFiltersConfig['rules'] ?? [];

    if (empty($rules)) {
      return true;
    }

    foreach ($rules as $rule) {
      $field = $rule['field'] ?? 'NAME';
      $operator = $rule['operator'] ?? 'regex';
      $val = $offerData[$field] ?? '';

      if ($operator === 'regex') {
        $pattern = $rule['pattern'] ?? '//';
        $isMatched = (bool)preg_match($pattern, (string)$val);

        if ($mode === 'whitelist' && !$isMatched) {
          return false;
        }

        if ($mode === 'blacklist' && $isMatched) {
          return false;
        }
      }
    }

    return true;
  }
}