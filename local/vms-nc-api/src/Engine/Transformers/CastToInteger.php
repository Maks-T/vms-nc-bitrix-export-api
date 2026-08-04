<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Transformers;

final class CastToInteger implements ValueTransformerInterface
{
  /**
   * @param mixed $value
   * @param array $options
   * @return int|null
   */
  public function transform($value, array $options = []): ?int
  {
    if ($value === null || $value === '') {
      return null;
    }

    return (int)$value;
  }
}