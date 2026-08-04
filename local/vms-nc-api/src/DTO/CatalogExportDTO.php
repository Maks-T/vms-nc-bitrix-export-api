<?php

declare(strict_types=1);

namespace VmsNcApi\DTO;

final class CatalogExportDTO
{
  public array $languages = [];
  public array $families = [];
  public array $types = [];
  /** @var CategoryDTO[] */
  public array $categories = [];
  public array $price_groups = [];
  public array $complex_dictionaries = [];
  /** @var AttributeDTO[] */
  public array $attributes = [];
  /** @var ProductDTO[] */
  public array $products = [];
  public array $currencies = [];
  public array $price_types = [];

  public function toArray(): array
  {
    return [
      'languages'            => $this->languages,
      'families'             => $this->families,
      'types'                => $this->types,
      'categories'           => array_map(fn($c) => $c instanceof CategoryDTO ? $c->toArray() : $c, $this->categories),
      'price_groups'         => $this->price_groups,
      'complex_dictionaries' => $this->complex_dictionaries,
      'attributes'           => array_map(fn($a) => $a instanceof AttributeDTO ? $a->toArray() : $a, $this->attributes),
      'products'             => array_map(fn($p) => $p instanceof ProductDTO ? $p->toArray() : $p, $this->products),
      'currencies'           => $this->currencies,
      'price_types'          => $this->price_types,
    ];
  }

}