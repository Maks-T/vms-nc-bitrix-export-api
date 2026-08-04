<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Transformers;

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
      $class   = is_array($config) ? ($config['class'] ?? '') : (string)$config;
      $options = is_array($config) ? ($config['options'] ?? []) : [];

      if (strpos($class, '\\') === false) {
        $class = "VmsNcApi\\Engine\\Transformers\\$class";
      }

      if (class_exists($class)) {
        /** @var ValueTransformerInterface $transformer */
        $transformer = new $class();
        $currentValue = $transformer->transform($currentValue, $options);
      }
    }

    return $currentValue;
  }

}