<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Filters;

use VmsNcApi\Constants\FilterMode;
use VmsNcApi\Engine\Conditions\ConditionEvaluator;

final class OfferFilter
{
  public static function isOfferAllowed(array $offerData, array $offerFiltersConfig): bool
  {
    $mode = $offerFiltersConfig['mode'] ?? FilterMode::BLACKLIST;
    $rules = $offerFiltersConfig['rules'] ?? [];

    if (empty($rules)) {
      return true;
    }

    $context = [
      'variant' => $offerData,
      'bitrix_element' => $offerData,
      'bitrix_properties' => $offerData['properties'] ?? [],
    ];

    foreach ($rules as $rule) {
      $isMatched = false;

      if (isset($rule['when']) || isset($rule['conditions']) || isset($rule['condition'])) {
        $isMatched = ConditionEvaluator::matches($rule, $context);
      } else {
        $field = $rule['field'] ?? 'ALL';
        $operator = $rule['operator'] ?? 'regex';

        if ($field === 'ALL' || $field === 'NAME_AND_CODE') {
          $val = ($offerData['NAME'] ?? '') . ' ' . ($offerData['CODE'] ?? '');
        } else {
          $val = $offerData[$field] ?? '';
        }

        if ($operator === 'regex') {
          $pattern = $rule['pattern'] ?? '//';
          $isMatched = (bool)preg_match($pattern, (string)$val);
        }
      }

      if ($mode === FilterMode::WHITELIST && !$isMatched) {
        return false;
      }

      if ($mode === FilterMode::BLACKLIST && $isMatched) {
        return false;
      }
    }

    return true;
  }
}