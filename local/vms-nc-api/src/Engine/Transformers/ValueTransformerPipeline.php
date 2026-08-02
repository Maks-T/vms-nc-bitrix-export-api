<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Transformers;

use VmsNcApi\Engine\Transformers\ValueTransformerInterface;

final class ValueTransformerPipeline
{
  /**
   * Прогоняет значение через цепочку трансформеров
   *
   * @param mixed $initialValue
   * @param array $transformersConfig
   * @return mixed
   */
  public static function process($initialValue, array $transformersConfig)
  {
    $currentValue = $initialValue;

    foreach ($transformersConfig as $config) {
      $transformerName = is_array($config) ? ($config['class'] ?? '') : $config;
      $options         = is_array($config) ? ($config['options'] ?? []) : [];

      $class = str_contains($transformerName, '\\')
        ? $transformerName
        : "VmsNcApi\\Engine\\Transformers\\{$transformerName}";

      if (class_exists($class)) {
        /** @var ValueTransformerInterface $transformer */
        $transformer = new $class();
        $currentValue = $transformer->transform($currentValue, $options);
      }
    }

    return $currentValue;
  }
}