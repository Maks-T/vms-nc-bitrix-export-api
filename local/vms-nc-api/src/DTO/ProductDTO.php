<?php

declare(strict_types=1);

namespace VmsNcApi\DTO;

final class ProductDTO
{
  public string $code;
  public string $externalCode;
  public string $productTypeExternalCode;
  public ?string $categoryExternalCode;
  public string $catalogType;
  public string $unitCode;
  public string $slug;
  public array $name;
  public ?string $previewPicture;
  public ?string $detailPicture;
  public array $eav;
  public bool $isActive;
  /** @var VariantDTO[] */
  public array $variants;

  public function __construct(
    string $code,
    string $externalCode,
    string $productTypeExternalCode,
    ?string $categoryExternalCode,
    string $catalogType,
    string $unitCode,
    string $slug,
    array $name,
    ?string $previewPicture,
    ?string $detailPicture,
    array $eav,
    bool $isActive = true,
    array $variants = []
  ) {
    $this->code                     = $code;
    $this->externalCode             = $externalCode;
    $this->productTypeExternalCode = $productTypeExternalCode;
    $this->categoryExternalCode    = $categoryExternalCode;
    $this->catalogType              = $catalogType;
    $this->unitCode                 = $unitCode;
    $this->slug                     = $slug;
    $this->name                     = $name;
    $this->previewPicture           = $previewPicture;
    $this->detailPicture            = $detailPicture;
    $this->eav                      = $eav;
    $this->isActive                 = $isActive;
    $this->variants                 = $variants;
  }

  public function toArray(): array
  {
    return [
      'code'                       => $this->code,
      'external_code'              => $this->externalCode,
      'product_type_external_code' => $this->productTypeExternalCode,
      'category_external_code'     => $this->categoryExternalCode,
      'catalog_type'               => $this->catalogType,
      'unit_code'                  => $this->unitCode,
      'slug'                       => $this->slug,
      'name'                       => $this->name,
      'preview_picture'            => $this->previewPicture,
      'detail_picture'             => $this->detailPicture,
      'eav'                        => $this->eav,
      'is_active'                  => $this->isActive,
      'variants'                   => array_map(fn($v) => $v instanceof VariantDTO ? $v->toArray() : $v, $this->variants),
    ];
  }

}