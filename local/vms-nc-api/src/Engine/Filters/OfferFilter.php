<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Filters;

use VmsNcApi\Constants\FilterMode;

final class OfferFilter
{
  /**
   * Проверяет, подходит ли торговое предложение под правила фильтрации клиента
   */
  public static function isOfferAllowed(array $offerData, array $offerFiltersConfig): bool
  {
    $mode  = $offerFiltersConfig['mode'] ?? FilterMode::WHITELIST;
    $rules = $offerFiltersConfig['rules'] ?? [];

    if (empty($rules)) {
      return true;
    }

    foreach ($rules as $rule) {
      $field    = $rule['field'] ?? 'ALL';
      $operator = $rule['operator'] ?? 'regex';

      if ($field === 'ALL' || $field === 'NAME_AND_CODE') {
        $val = ($offerData['NAME'] ?? '') . ' ' . ($offerData['CODE'] ?? '');
      } else {
        $val = $offerData[$field] ?? '';
      }

      if ($operator === 'regex') {
        $pattern   = $rule['pattern'] ?? '//';
        $isMatched = (bool)preg_match($pattern, (string)$val);

        if ($mode === FilterMode::WHITELIST && !$isMatched) {
          return false;
        }

        if ($mode === FilterMode::BLACKLIST && $isMatched) {
          return false;
        }
      }
    }

    return true;
  }

}