<?php

declare(strict_types=1);

namespace VmsNcApi\DTO;

final class VariantDTO
{
  public string $externalCode;
  public string $sku;
  public ?string $name;
  public ?string $priceGroupExternalCode;
  public float $stock;
  public bool $isDefault;
  public ?string $previewPicture;
  public ?string $detailPicture;
  public array $eav;
  public bool $isManualPricing;
  public ?float $costPrice;
  public string $currency;
  public float $markupPercent;

  public function __construct(
    string $externalCode,
    string $sku,
    ?string $name,
    ?string $priceGroupExternalCode,
    float $stock,
    bool $isDefault,
    ?string $previewPicture,
    ?string $detailPicture,
    array $eav,
    bool $isManualPricing,
    ?float $costPrice,
    string $currency,
    float $markupPercent = 0.0
  ) {
    $this->externalCode           = $externalCode;
    $this->sku                    = $sku;
    $this->name                   = $name;
    $this->priceGroupExternalCode = $priceGroupExternalCode;
    $this->stock                  = $stock;
    $this->isDefault              = $isDefault;
    $this->previewPicture         = $previewPicture;
    $this->detailPicture          = $detailPicture;
    $this->eav                    = $eav;
    $this->isManualPricing        = $isManualPricing;
    $this->costPrice              = $costPrice;
    $this->currency               = $currency;
    $this->markupPercent          = $markupPercent;
  }

  public function toArray(): array
  {
    return [
      'external_code'             => $this->externalCode,
      'sku'                       => $this->sku,
      'name'                      => $this->name,
      'price_group_external_code' => $this->priceGroupExternalCode,
      'stock'                     => $this->stock,
      'is_default'                => $this->isDefault,
      'preview_picture'           => $this->previewPicture,
      'detail_picture'            => $this->detailPicture,
      'eav'                       => $this->eav,
      'is_manual_pricing'         => $this->isManualPricing,
      'cost_price'                => $this->costPrice,
      'currency'                  => $this->currency,
      'markup_percent'            => $this->markupPercent,
    ];
  }

}