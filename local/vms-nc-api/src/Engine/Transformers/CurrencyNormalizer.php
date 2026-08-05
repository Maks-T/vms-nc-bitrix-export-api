<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Transformers;

final class CurrencyNormalizer
{
  /**
   * Нормализует сырую цену и валюту на основе правил клиента
   *
   * @param float $rawPrice Исходная цена из Битрикс (например 889.0)
   * @param string $rawCurrency Исходная валюта из Битрикс (например 'BLA')
   * @param array $rulesMap Массив правил нормализации из конфига клиента
   * @return array ['cost_price' => float, 'currency' => string, 'markup_percent' => float]
   */
  public static function normalize(float $rawPrice, string $rawCurrency, array $rulesMap = []): array
  {
    $currency = strtoupper(trim($rawCurrency));

    // Если для этой псевдовалюты заведено правило в конфиге
    if (isset($rulesMap[$currency])) {
      $rule = $rulesMap[$currency];

      return [
        'cost_price'     => $rawPrice,
        'currency'       => (string)($rule['target_currency'] ?? $currency),
        'markup_percent' => (float)($rule['markup_percent'] ?? 0.0),
      ];
    }

    // Если правил нет (обычная чистая валюта RUB, USD, EUR)
    return [
      'cost_price'     => $rawPrice,
      'currency'       => $currency,
      'markup_percent' => 0.0,
    ];
  }
}