<?php

declare(strict_types=1);

namespace VmsNcApi\DTO;

final class AttributeOptionDTO
{
  public string $externalCode;
  public string $slug;
  public array $value;
  public array $meta;
  public ?string $param;

  public function __construct(string $externalCode, string $slug, array $value, array $meta = [], ?string $param = null)
  {
    $this->externalCode = $externalCode;
    $this->slug         = $slug;
    $this->value        = $value;
    $this->meta         = $meta;
    $this->param        = $param ?? $slug;
  }

  public function toArray(): array
  {
    return [
      'external_code' => $this->externalCode,
      'slug'          => $this->slug,
      'value'         => $this->value,
      'meta'          => $this->meta,
      'param'         => $this->param,
    ];
  }
}