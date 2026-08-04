<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Exporters;

use VmsNcApi\Engine\Contracts\EntityExporterInterface;

final class PriceTypeExporter implements EntityExporterInterface
{
  public function export(array $clientConfig, array $industryConfig = [], string $entityKey = ''): array
  {
    return $clientConfig['price_types'] ?? [];
  }

}