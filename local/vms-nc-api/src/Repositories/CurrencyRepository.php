<?php

declare(strict_types=1);

namespace VmsNcApi\Repositories;

use Bitrix\Currency\CurrencyTable;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use RuntimeException;

final class CurrencyRepository
{
  public function __construct()
  {
    try {
      if (!Loader::includeModule('currency') && !Loader::includeModule('catalog')) {
        throw new RuntimeException('Модуль currency / catalog не установлен');
      }
    } catch (LoaderException $e) {
      throw new RuntimeException('Ошибка загрузки модулей Битрикс: ' . $e->getMessage());
    }
  }

  /**
   * Получить текущий курс валюты к Рублю через D7 CurrencyTable
   *
   * @param string $currencyCode Код валюты (например 'USD', 'EUR', 'BYN')
   * @return float
   */
  public function getCurrencyRate(string $currencyCode): float
  {
    if ($currencyCode === 'RUB') {
      return 1.0;
    }

    try {
      $curr = CurrencyTable::getList([
        'filter' => ['=CURRENCY' => $currencyCode],
        'select' => ['AMOUNT', 'AMOUNT_CNT']
      ])->fetch();

      if ($curr && (float)$curr['AMOUNT'] > 0) {
        $amount = (float)$curr['AMOUNT'];
        $amountCnt = (float)($curr['AMOUNT_CNT'] ?? 1.0);
        return round($amount / ($amountCnt > 0 ? $amountCnt : 1.0), 4);
      }
    } catch (\Throwable $e) {
      // Резервный возврат при сбое
    }

    return 1.0;
  }
}