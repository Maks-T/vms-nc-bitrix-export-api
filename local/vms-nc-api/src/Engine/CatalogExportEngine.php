<?php

declare(strict_types=1);

namespace VmsNcApi\Engine;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Throwable;
use VmsNcApi\DTO\CatalogExportDTO;
use VmsNcApi\Engine\Contracts\EntityExporterInterface;
use VmsNcApi\Engine\Exporters\DynamicCurrencyExporter;
use VmsNcApi\Engine\Exporters\IblockAttributeExporter;
use VmsNcApi\Engine\Exporters\IblockCategoryExporter;
use VmsNcApi\Engine\Exporters\IblockProductExporter;
use VmsNcApi\Engine\Exporters\PriceTypeExporter;
use VmsNcApi\Engine\Exporters\StaticIndustryExporter;

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
    $clientConfig['query_params'] = $queryParams; // Передаем параметры запроса

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