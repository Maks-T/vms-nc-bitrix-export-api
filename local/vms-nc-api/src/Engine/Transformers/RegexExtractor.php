<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Transformers;

final class RegexExtractor implements ValueTransformerInterface
{
  /**
   * Извлекает число или подстроку из текста по регулярному выражению
   *
   * @param mixed $value Исходный текст (например "3680x760x12 (целый лист)")
   * @param array $options ['pattern' => '...', 'group' => 1]
   * @return int|string|null
   */
  public function transform($value, array $options = [])
  {
    if (!is_string($value) || empty($value)) {
      return null;
    }

    $pattern = isset($options['pattern']) ? (string)$options['pattern'] : '/(\d+)[\*xх×](\d+)[\*xх×](\d+)/ui';
    $group   = isset($options['group']) ? (int)$options['group'] : 1;

    if (preg_match($pattern, $value, $matches)) {
      $result = isset($matches[$group]) ? trim($matches[$group]) : null;
      return is_numeric($result) ? (int)$result : $result;
    }

    return null;
  }
}