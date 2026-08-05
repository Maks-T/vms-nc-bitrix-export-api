<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Exporters;

use VmsNcApi\Engine\Contracts\EntityExporterInterface;
use VmsNcApi\Repositories\CurrencyRepository;
use VmsNcApi\Helpers\LocalizationHelper;

final class DynamicCurrencyExporter implements EntityExporterInterface
{
  private CurrencyRepository $currencyRepo;

  public function __construct(CurrencyRepository $currencyRepo)
  {
    $this->currencyRepo = $currencyRepo;
  }

  public function export(array $clientConfig, array $industryConfig = [], string $entityKey = ''): array
  {
    $configCurrencies = $clientConfig['currencies'] ?? [];
    $locales          = $clientConfig['locales'] ?? ['ru'];
    $result           = [];

    foreach ($configCurrencies as $code => $data) {
      $isDefault = (bool)($data['is_default'] ?? false);

      if (!empty($data['use_live_rate'])) {
        $rate = $this->currencyRepo->getCurrencyRate($code, $isDefault);
      } else {
        $rate = (float)($data['rate'] ?? 1.0);
      }

      $symbol  = (string)($data['symbol'] ?? $code);
      $rawName = $data['name'] ?? $code;

      $result[] = [
        'code'          => $code,
        'symbol'        => $symbol,
        'symbol_native' => LocalizationHelper::format($symbol, $locales),
        'name'          => LocalizationHelper::format($rawName, $locales),
        'rate'          => $rate,
        'is_default'    => $isDefault,
        'is_active'     => true,
      ];
    }

    return $result;
  }
}