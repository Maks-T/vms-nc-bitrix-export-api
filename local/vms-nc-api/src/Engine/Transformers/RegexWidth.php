<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Transformers;

final class RegexWidth implements ValueTransformerInterface
{
  public function transform(mixed $value, array $options = []): mixed
  {
    if (!is_string($value) || empty($value)) {
      return null;
    }

    if (preg_match('/(\d+)[\*xх×](\d+)[\*xх×](\d+)/ui', $value, $matches)) {
      return (int)$matches[2];
    }

    return null;
  }
}