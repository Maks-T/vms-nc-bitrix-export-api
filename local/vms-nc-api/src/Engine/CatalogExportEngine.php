<?php

declare(strict_types=1);

namespace VmsNcApi\Engine;

use RuntimeException;
use VmsNcApi\DTO\CatalogExportDTO;
use VmsNcApi\Repositories\CurrencyRepository;
use VmsNcApi\Repositories\HlRepository;
use VmsNcApi\Repositories\IblockRepository;

final class CatalogExportEngine
{
  /** @var IblockRepository */
  private $iblockRepo;

  /** @var HlRepository */
  private $hlRepo;

  /** @var CurrencyRepository */
  private $currencyRepo;

  public function __construct(
    IblockRepository $iblockRepo,
    HlRepository $hlRepo,
    CurrencyRepository $currencyRepo
  ) {
    $this->iblockRepo   = $iblockRepo;
    $this->hlRepo       = $hlRepo;
    $this->currencyRepo = $currencyRepo;
  }

  public function export(string $clientCode): CatalogExportDTO
  {
    $configPath = __DIR__ . "/../../config/catalogs/{$clientCode}.php";
    if (!file_exists($configPath)) {
      throw new RuntimeException("Конфигурация клиента '{$clientCode}' не найдена.", 404);
    }

    $clientConfig = require $configPath;
    $industryCode = (string)($clientConfig['industry'] ?? 'stone');

    $industryPath = __DIR__ . "/../../config/industries/{$industryCode}.php";
    $industryConfig = file_exists($industryPath) ? require $industryPath : [];

    $dto = new CatalogExportDTO();

    // 1. Динамическая настройка Валют и Цен из Битрикса
    $dto->currencies  = $this->formatCurrencies($clientConfig['currencies'] ?? []);
    $dto->price_types = $clientConfig['price_types'] ?? [];

    // 2. Индустрия (Семейства, Типы, Ценовые группы, Умные справочники)
    $dto->families             = $industryConfig['families'] ?? [];
    $dto->types                = $industryConfig['types'] ?? [];
    $dto->price_groups         = $industryConfig['price_groups'] ?? [];
    $dto->complex_dictionaries = $industryConfig['complex_dictionaries'] ?? [];

    // 3. Категории
    $dto->categories = $this->iblockRepo->getCategories($clientConfig);

    // 4. Атрибуты
    $dto->attributes = $this->iblockRepo->getAttributesWithDictionaries($clientConfig);

    // 5. Выборка и сборка товаров с вариациями (SKU)
    $dto->products = $this->iblockRepo->getProducts($clientConfig);

    return $dto;
  }

  /**
   * Форматирует валюты с АВТОМАТИЧЕСКИМ ПОЛУЧЕНИЕМ КУРСА из Битрикса
   */
  private function formatCurrencies(array $configCurrencies): array
  {
    $result = [];
    foreach ($configCurrencies as $code => $data) {
      // Автоматически запрашиваем живой курс из базы Битрикса!
      $liveRate = $this->currencyRepo->getCurrencyRate($code);

      $result[] = [
        'code'          => $code,
        'symbol'        => $data['symbol'] ?? $code,
        'symbol_native' => ['ru' => $data['symbol'] ?? $code, 'en' => $data['symbol'] ?? $code],
        'name'          => ['ru' => $data['name']['ru'] ?? $code, 'en' => $data['name']['en'] ?? $code],
        'rate'          => $liveRate,
        'is_default'    => (bool)($data['is_default'] ?? false),
        'is_active'     => true,
      ];
    }
    return $result;
  }
}