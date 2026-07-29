<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Transformers;

interface ValueTransformerInterface
{
  /**
   * Трансформирует значение свойства
   *
   * @param mixed $value Исходное значение
   * @param array $options Опции трансформера
   * @return mixed
   */
  public function transform($value, array $options = []);
}