<?php

declare(strict_types=1);

namespace VmsNcApi\Engine;

use Psr\Container\ContainerInterface;
use RuntimeException;
use VmsNcApi\DTO\CatalogExportDTO;
use VmsNcApi\Engine\Contracts\EntityExporterInterface;
use VmsNcApi\Engine\Exporters\DynamicCurrencyExporter;
use VmsNcApi\Engine\Exporters\IblockAttributeExporter;
use VmsNcApi\Engine\Exporters\IblockCategoryExporter;
use VmsNcApi\Engine\Exporters\IblockProductExporter;
use VmsNcApi\Engine\Exporters\PriceTypeExporter;
use VmsNcApi\Engine\Exporters\StaticIndustryExporter;
use VmsNcApi\Services\LogService;

final class CatalogExportEngine
{
  private ContainerInterface $container;

  public function __construct(ContainerInterface $container)
  {
    $this->container = $container;
  }

  public function export(string $clientCode, array $queryParams = []): CatalogExportDTO
  {
    $configPath = __DIR__ . "/../../config/catalogs/$clientCode.php";
    if (!file_exists($configPath)) {
      throw new RuntimeException("Конфигурация клиента '$clientCode' не найдена.", 404);
    }

    $clientConfig  = require $configPath;
    $clientConfig['query_params'] = $queryParams;

    $industryCode  = (string)($clientConfig['industry'] ?? 'stone');
    $industryPath  = __DIR__ . "/../../config/industries/$industryCode.php";
    $industryConfig = file_exists($industryPath) ? require $industryPath : [];

    $exportersMap = $clientConfig['exporters'] ?? $this->getDefaultExportersMap();

    $dto = new CatalogExportDTO();
    $dto->languages = array_values($clientConfig['locales'] ?? ['ru']);

    foreach ($exportersMap as $entityKey => $exporterClass) {
      if (!class_exists($exporterClass)) {
        throw new RuntimeException("Класс экспортера '$exporterClass' для сущности '$entityKey' не найден.");
      }

      /** @var EntityExporterInterface $exporter */
      $exporter = $this->container->get($exporterClass);

      if (property_exists($dto, $entityKey)) {
        $dto->{$entityKey} = $exporter->export($clientConfig, $industryConfig, $entityKey);
      }
    }

    $customJsonPath = __DIR__ . "/../../config/catalogs/{$clientCode}_custom.json";
    if (file_exists($customJsonPath)) {
      $rawContent = (string)file_get_contents($customJsonPath);
      $customData = json_decode($rawContent, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        LogService::error("Синтаксическая ошибка в JSON $customJsonPath: " . json_last_error_msg());
      } elseif (is_array($customData)) {

        if (isset($customData[0]) && is_array($customData[0])) {
          $dto->products = array_merge($dto->products, $customData);
        } else {

          foreach ($customData as $key => $items) {
            if (property_exists($dto, $key) && is_array($items)) {
              $dto->{$key} = array_merge($dto->{$key}, $items);
            }
          }
        }
      }
    }

    return $dto;
  }

  private function getDefaultExportersMap(): array
  {
    return [
      'currencies'           => DynamicCurrencyExporter::class,
      'price_types'          => PriceTypeExporter::class,
      'families'             => StaticIndustryExporter::class,
      'types'                => StaticIndustryExporter::class,
      'price_groups'         => StaticIndustryExporter::class,
      'complex_dictionaries' => StaticIndustryExporter::class,
      'categories'           => IblockCategoryExporter::class,
      'attributes'           => IblockAttributeExporter::class,
      'products'             => IblockProductExporter::class,
    ];
  }
}