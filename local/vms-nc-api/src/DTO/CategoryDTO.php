<?php

declare(strict_types=1);

namespace VmsNcApi\DTO;

final class CategoryDTO
{
  public string $externalCode;
  public ?string $parentExternalCode;
  public string $slug;
  public array $name;

  public function __construct(string $externalCode, ?string $parentExternalCode, string $slug, array $name)
  {
    $this->externalCode       = $externalCode;
    $this->parentExternalCode = $parentExternalCode;
    $this->slug               = $slug;
    $this->name               = $name;
  }

  public static function fromArray(array $data): self
  {
    return new self(
      (string)($data['external_code'] ?? ''),
      !empty($data['parent_external_code']) ? (string)$data['parent_external_code'] : null,
      (string)($data['slug'] ?? ''),
      (array)($data['name'] ?? [])
    );
  }

  public function toArray(): array
  {
    return [
      'external_code'        => $this->externalCode,
      'parent_external_code' => $this->parentExternalCode,
      'slug'                 => $this->slug,
      'name'                 => $this->name,
    ];
  }

}