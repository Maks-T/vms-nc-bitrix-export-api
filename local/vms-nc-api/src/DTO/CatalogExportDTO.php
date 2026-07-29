<?php

declare(strict_types=1);

namespace VmsNcApi\DTO;

final class CatalogExportDTO
{
  /** @var array */
  public $languages = [];

  /** @var array */
  public $families = [];

  /** @var array */
  public $types = [];

  /** @var array */
  public $categories = [];

  /** @var array */
  public $price_groups = [];

  /** @var array */
  public $complex_dictionaries = [];

  /** @var array */
  public $attributes = [];

  /** @var array */
  public $products = [];

  /** @var array */
  public $currencies = [];

  /** @var array */
  public $price_types = [];

  public function toArray(): array
  {
    return [
      'languages'            => $this->languages,
      'families'             => $this->families,
      'types'                => $this->types,
      'categories'           => $this->categories,
      'price_groups'         => $this->price_groups,
      'complex_dictionaries' => $this->complex_dictionaries,
      'attributes'           => $this->attributes,
      'products'             => $this->products,
      'currencies'           => $this->currencies,
      'price_types'          => $this->price_types,
    ];
  }
}