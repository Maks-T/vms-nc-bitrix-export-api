<?php

declare(strict_types=1);

namespace VmsNcApi\Repositories;

use Bitrix\Currency\CurrencyTable;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use RuntimeException;
use Throwable;

final class CurrencyRepository
{
  /**
   * @throws LoaderException
   */
  public function __construct()
  {
    if (!Loader::includeModule('currency') && !Loader::includeModule('catalog')) {
      throw new RuntimeException('Модуль currency / catalog не установлен');
    }
  }

  /**
   * Получить текущий курс валюты относительно базовой
   *
   * @param string $currencyCode Код валюты (например 'USD', 'EUR', 'BYN')
   * @param bool $isDefault
   * @return float
   */
  public function getCurrencyRate(string $currencyCode, bool $isDefault = false): float
  {
    // Базовая валюта всегда имеет курс 1.0
    if ($isDefault || $currencyCode === 'RUB') { // Если это дефолтная валюта из конфига
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
    } catch (Throwable $e) {
      // Резервный возврат при сбое
    }

    return 1.0;
  }

}