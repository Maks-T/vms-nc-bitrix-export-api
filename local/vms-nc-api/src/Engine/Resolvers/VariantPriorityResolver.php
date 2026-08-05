<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Resolvers;

final class VariantPriorityResolver
{
  /**
   * Чистый доменный резолвер приоритета вариаций на основе сформированных EAV-данных
   */
  public static function resolve(array $variants, string $productTypeCode, array $rulesConfig): array
  {
    if (empty($variants) || !isset($rulesConfig[$productTypeCode])) {
      return $variants;
    }

    $config = $rulesConfig[$productTypeCode];
    $mode   = $config['mode'] ?? 'set_default';
    $rules  = $config['rules'] ?? [];

    if (empty($rules)) {
      return $variants;
    }

    foreach ($variants as &$variant) {
      $score = 0.0;
      $eav   = $variant['eav'] ?? [];

      $ruleWeight = 1000000.0;

      foreach ($rules as $rule) {
        $attrCode = $rule['attribute'] ?? '';
        $type     = $rule['type'] ?? 'sort';

        // Берем значение strictly из готового EAV
        $val = $eav[$attrCode] ?? ($variant[$attrCode] ?? null);

        if ($type === 'match_order' && is_array($rule['values'] ?? null)) {
          $targetValues = array_map('intval', $rule['values']);
          $position     = array_search((int)$val, $targetValues, true);
          if ($position !== false) {
            $points = count($targetValues) - $position;
            $score += $points * $ruleWeight;
          }
        } elseif ($type === 'sort') {
          $order  = $rule['order'] ?? 'desc';
          $numVal = is_numeric($val) ? (float)$val : 0.0;

          if ($order === 'desc' || $order === 'max') {
            $score += $numVal * ($ruleWeight / 10000.0);
          } else {
            $score += (100000.0 - $numVal) * ($ruleWeight / 10000.0);
          }
        }

        $ruleWeight /= 1000.0;
      }

      $variant['_priority_score'] = $score;
    }
    unset($variant);

    usort($variants, function ($a, $b) {
      return ($b['_priority_score'] ?? 0.0) <=> ($a['_priority_score'] ?? 0.0);
    });

    if ($mode === 'single_primary') {
      $best = $variants[0];
      $best['is_default'] = true;
      unset($best['_priority_score']);
      return [$best];
    }

    foreach ($variants as $i => &$v) {
      $v['is_default'] = ($i === 0);
      unset($v['_priority_score']);
    }

    return $variants;
  }
}