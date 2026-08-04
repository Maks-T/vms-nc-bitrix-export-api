<?php

declare(strict_types=1);

namespace VmsNcApi\Repositories;

use Bitrix\Catalog\PriceTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use RuntimeException;

final class CatalogPriceRepository
{
  /**
   * @throws LoaderException
   */
  public function __construct()
  {
    if (!Loader::includeModule('catalog')) {
      throw new RuntimeException('Модуль catalog не установлен');
    }
  }

  /**
   * Получить розничную и закупочную цену для товара/SKU согласно конфигу клиента
   *
   * @param int $productId ID элемента в Битрикс
   * @param array $clientConfig Конфигурация клиента
   * @return array ['price' => float, 'cost_price' => float, 'currency' => string]
   * @throws ObjectPropertyException
   * @throws SystemException
   * @throws ArgumentException
   */
  public function getProductPrice(int $productId, array $clientConfig = []): array
  {
    $pricingConfig = $clientConfig['pricing'] ?? [];
    $retailGroupId = (int)($pricingConfig['retail_group_id'] ?? 1);
    $costGroupId   = isset($pricingConfig['cost_group_id']) ? (int)$pricingConfig['cost_group_id'] : null;

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

      if ($groupId === $retailGroupId || ($retailPrice === 0.0 && $groupId !== $costGroupId)) {
        $retailPrice = (float)$p['PRICE'];
        $currency    = (string)$p['CURRENCY'];
      }

      if ($costGroupId !== null && $groupId === $costGroupId) {
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