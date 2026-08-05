<?php

declare(strict_types=1);

namespace VmsNcApi\Repositories;

use Bitrix\Catalog\PriceTable;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use RuntimeException;
use VmsNcApi\Engine\Transformers\CurrencyNormalizer;

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
   * Пакетная выборка цен с нормализацией валют и наценок
   */
  public function getPricesBatch(array $elementIds, array $clientConfig = []): array
  {
    if (empty($elementIds)) {
      return [];
    }

    $pricingConfig  = $clientConfig['pricing'] ?? [];
    $retailGroupId  = (int)($pricingConfig['retail_group_id'] ?? 1);
    $converterRules = $clientConfig['currency_converter']['rules'] ?? [];

    $priceRows = PriceTable::getList([
      'filter' => ['@PRODUCT_ID' => $elementIds],
      'select' => ['PRODUCT_ID', 'PRICE', 'CURRENCY', 'CATALOG_GROUP_ID']
    ])->fetchAll();

    $result = [];
    foreach ($priceRows as $p) {
      $prodId   = (int)$p['PRODUCT_ID'];
      $groupId  = (int)$p['CATALOG_GROUP_ID'];
      $rawPrice = (float)$p['PRICE'];
      $rawCurr  = (string)$p['CURRENCY'];

      if ($groupId === $retailGroupId || !isset($result[$prodId])) {
        $norm = CurrencyNormalizer::normalize($rawPrice, $rawCurr, $converterRules);

        $result[$prodId] = [
          'cost_price'     => $norm['cost_price'],
          'currency'       => $norm['currency'],
          'markup_percent' => $norm['markup_percent'],
        ];
      }
    }

    return $result;
  }

}