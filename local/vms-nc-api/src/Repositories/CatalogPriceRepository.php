<?php

declare(strict_types=1);

namespace VmsNcApi\Repositories;

use Bitrix\Catalog\PriceTable;
use Bitrix\Main\Loader;
use RuntimeException;

final class CatalogPriceRepository
{
  public function __construct()
  {
    if (!Loader::includeModule('catalog')) {
      throw new RuntimeException('Модуль catalog не установлен');
    }
  }

  /**
   * Получить розничную и закупочную цену для товара/SKU
   *
   * @param int $productId ID элемента
   * @return array ['price' => float, 'cost_price' => float, 'currency' => string]
   */
  public function getProductPrice(int $productId): array
  {
    $priceRes = PriceTable::getList([
      'filter' => ['=PRODUCT_ID' => $productId],
      'select' => ['PRICE', 'CURRENCY', 'CATALOG_GROUP_ID']
    ])->fetchAll();

    if (empty($priceRes)) {
      return [
        'price'      => 0.0,
        'cost_price' => 0.0,
        'currency'   => 'USD'
      ];
    }

    $retailPrice = 0.0;
    $costPrice   = 0.0;
    $currency    = 'USD';

    foreach ($priceRes as $p) {
      $groupId = (int)$p['CATALOG_GROUP_ID'];

      // Розничная цена (BASE, GROUP_ID = 1)
      if ($groupId === 1) {
        $retailPrice = (float)$p['PRICE'];
        $currency    = (string)$p['CURRENCY'];
      }

      // Закупочная цена (ZAKUPKA, GROUP_ID = 2)
      if ($groupId === 2) {
        $costPrice = (float)$p['PRICE'];
      }
    }

    return [
      'price'      => $retailPrice,
      'cost_price' => $costPrice > 0 ? $costPrice : $retailPrice,
      'currency'   => $currency
    ];
  }
}