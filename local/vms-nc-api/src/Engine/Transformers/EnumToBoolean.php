<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Transformers;

final class EnumToBoolean implements ValueTransformerInterface
{
  public function transform($value, array $options = [])
  {
    if ($value === null || $value === '') {
      return false;
    }

    $truthyValues = isset($options['truthy']) && is_array($options['truthy'])
      ? $options['truthy']
      : ['yes', 'y', 'yes_bend', 'yes_repeat', 'yes_separate', '1', 'true', 'да'];

    $val = mb_strtolower((string)$value);

    return in_array($val, $truthyValues, true);
  }
}