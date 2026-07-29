<?php

declare(strict_types=1);

namespace VmsNcApi\Engine;

use VmsNcApi\Engine\Transformers\ValueTransformerInterface;

final class ValueTransformerPipeline
{
  /**
   * Прогоняет значение через цепочку трансформеров
   *
   * @param mixed $initialValue
   * @param array $transformerNames Массив имен классов трансформеров
   * @return mixed
   */
  public static function process($initialValue, array $transformerNames)
  {
    $currentValue = $initialValue;

    foreach ($transformerNames as $transformerName) {
      $class = str_contains($transformerName, '\\')
        ? $transformerName
        : "VmsNcApi\\Engine\\Transformers\\{$transformerName}";

      if (class_exists($class)) {
        /** @var ValueTransformerInterface $transformer */
        $transformer = new $class();
        $currentValue = $transformer->transform($currentValue);
      }
    }

    return $currentValue;
  }
}