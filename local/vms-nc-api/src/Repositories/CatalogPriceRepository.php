<?php

declare(strict_types=1);

namespace VmsNcApi\Repositories;

use Bitrix\Catalog\PriceTable;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
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
   * Пакетная выборка цен
   *
   * @param array $elementIds Массив ID элементов
   * @param array $clientConfig Конфиг клиента
   * @return array [element_id => ['price' => float, 'cost_price' => float, 'currency' => string]]
   */
  public function getPricesBatch(array $elementIds, array $clientConfig = []): array
  {
    if (empty($elementIds)) {
      return [];
    }

    $pricingConfig = $clientConfig['pricing'] ?? [];
    $retailGroupId = (int)($pricingConfig['retail_group_id'] ?? 1);
    $costGroupId   = isset($pricingConfig['cost_group_id']) ? (int)$pricingConfig['cost_group_id'] : null;

    $priceRows = PriceTable::getList([
      'filter' => ['@PRODUCT_ID' => $elementIds],
      'select' => ['PRODUCT_ID', 'PRICE', 'CURRENCY', 'CATALOG_GROUP_ID']
    ])->fetchAll();

    $result = [];
    foreach ($priceRows as $p) {
      $prodId  = (int)$p['PRODUCT_ID'];
      $groupId = (int)$p['CATALOG_GROUP_ID'];
      $price   = (float)$p['PRICE'];
      $curr    = (string)$p['CURRENCY'];

      if (!isset($result[$prodId])) {
        $result[$prodId] = [
          'price'      => 0.0,
          'cost_price' => 0.0,
          'currency'   => $curr ?: 'USD'
        ];
      }

      if ($groupId === $retailGroupId || ($result[$prodId]['price'] === 0.0 && $groupId !== $costGroupId)) {
        $result[$prodId]['price']    = $price;
        $result[$prodId]['currency'] = $curr;
      }

      if ($costGroupId !== null && $groupId === $costGroupId) {
        $result[$prodId]['cost_price'] = $price;
      }
    }

    // Нормализация пустых себестоимостей
    foreach ($result as $prodId => &$data) {
      if ($data['cost_price'] <= 0) {
        $data['cost_price'] = $data['price'];
      }
    }

    return $result;
  }

}