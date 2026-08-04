<?php

declare(strict_types=1);

namespace VmsNcApi\DTO;

final class AttributeDTO
{
  public string $externalCode;
  public string $code;
  public string $type;
  public array $name;
  public bool $isMultiple;
  /** @var AttributeOptionDTO[] */
  public array $options;
  public ?string $optionParamType;

  public function __construct(
    string $externalCode,
    string $code,
    string $type,
    array $name,
    bool $isMultiple = false,
    array $options = [],
    ?string $optionParamType = null
  ) {
    $this->externalCode    = $externalCode;
    $this->code            = $code;
    $this->type            = $type;
    $this->name            = $name;
    $this->isMultiple      = $isMultiple;
    $this->options         = $options;
    $this->optionParamType = $optionParamType;
  }

  public function toArray(): array
  {
    return [
      'external_code'     => $this->externalCode,
      'code'              => $this->code,
      'type'              => $this->type,
      'name'              => $this->name,
      'is_multiple'       => $this->isMultiple,
      'options'           => array_map(fn($opt) => $opt instanceof AttributeOptionDTO ? $opt->toArray() : $opt, $this->options),
      'option_param_type' => $this->optionParamType,
    ];
  }
}