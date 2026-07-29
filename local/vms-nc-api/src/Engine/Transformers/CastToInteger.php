<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Transformers;

final class CastToInteger implements ValueTransformerInterface
{
  public function transform($value, array $options = [])
  {
    if ($value === null || $value === '') {
      return null;
    }

    return (int)$value;
  }
}