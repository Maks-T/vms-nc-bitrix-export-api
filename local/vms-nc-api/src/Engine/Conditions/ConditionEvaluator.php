<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Conditions;

final class ConditionEvaluator
{
  /**
   * Проверяет совпадение условия с контекстом
   *
   * @param array $conditionRule Массив правила ['when' => [...], 'value' => ...]
   * @param array $context Единый контекст элемента Битрикс и контракта VMS-NC
   * @return bool
   */
  public static function matches(array $conditionRule, array $context): bool
  {
    $when = $conditionRule['when'] ?? ($conditionRule['condition'] ?? ($conditionRule['if'] ?? null));

    if (is_array($when) && !empty($when)) {
      foreach ($when as $fieldPath => $expectedValue) {
        $actualValue = self::resolveFieldPath((string)$fieldPath, $context);
        if (!self::compareValues($actualValue, '==', $expectedValue)) {
          return false;
        }
      }
      return true;
    }

    $conditions = $conditionRule['conditions'] ?? [];
    if (empty($conditions)) {
      return false;
    }

    foreach ($conditions as $cond) {
      $fieldPath = (string)($cond['field'] ?? '');
      $operator = (string)($cond['operator'] ?? '==');
      $expected = $cond['value'] ?? null;

      $actualValue = self::resolveFieldPath($fieldPath, $context);
      if (!self::compareValues($actualValue, $operator, $expected)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Полный резолвер по точечной нотации (bitrix.property, bitrix.field, product.type, product.eav)
   */
  private static function resolveFieldPath(string $path, array $context)
  {
    $parts = explode('.', $path);
    $scope = strtolower($parts[0] ?? '');

    if (count($parts) === 1) {
      return $context[$path] ?? null;
    }

    switch ($scope) {
      case 'bitrix':
        $subScope = strtolower($parts[1] ?? '');
        if ($subScope === 'property' || $subScope === 'prop') {
          $propCode = $parts[2] ?? '';
          return $context['bitrix_properties'][$propCode] ?? null;
        }
        if ($subScope === 'field') {
          $fieldName = strtoupper($parts[2] ?? '');
          return $context['bitrix_element'][$fieldName] ?? ($context['bitrix_element'][strtolower($fieldName)] ?? null);
        }
        return null;

      case 'product':
        $subField = strtolower($parts[1] ?? '');
        if ($subField === 'type' || $subField === 'product_type') {
          return $context['product_type'] ?? null;
        }
        if ($subField === 'eav') {
          $eavKey = $parts[2] ?? '';
          return $context['product_eav'][$eavKey] ?? null;
        }
        return $context['product'][$subField] ?? ($context['product'][strtoupper($subField)] ?? null);

      case 'variant':
        $subField = strtolower($parts[1] ?? '');
        if ($subField === 'eav') {
          $eavKey = $parts[2] ?? '';
          return $context['variant_eav'][$eavKey] ?? null;
        }
        return $context['variant'][$subField] ?? ($context['variant'][strtoupper($subField)] ?? null);

      default:
        return $context[$path] ?? null;
    }
  }

  /**
   * Сравнение значений
   */
  private static function compareValues($actual, string $operator, $expected): bool
  {

    if ($operator === '==' && is_string($expected) && is_string($actual)) {
      if (strpos($expected, '/') === 0 && (substr($expected, -1) === '/' || preg_match('/\/[a-z]*$/', $expected))) {
        $operator = 'regex';
      }
    }

    switch ($operator) {
      case '==':
      case 'equals':
        if (is_array($expected)) {
          return in_array($actual, $expected, true);
        }
        return (string)$actual === (string)$expected;

      case '!=':
      case 'not_equals':
        if (is_array($expected)) {
          return !in_array($actual, $expected, true);
        }
        return (string)$actual !== (string)$expected;

      case 'in':
        return is_array($expected) && in_array($actual, $expected, true);

      case 'regex':
      case 'match':
        return is_string($actual) && is_string($expected) && (bool)preg_match($expected, $actual);

      case 'not_empty':
        return !empty($actual);

      default:
        return false;
    }
  }

}