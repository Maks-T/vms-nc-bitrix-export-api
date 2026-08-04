<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Exporters;

use VmsNcApi\Engine\Contracts\EntityExporterInterface;

final class StaticIndustryExporter implements EntityExporterInterface
{
  public function export(array $clientConfig, array $industryConfig = [], string $entityKey = ''): array
  {
    if (isset($industryConfig[$entityKey]) && is_array($industryConfig[$entityKey])) {
      return $industryConfig[$entityKey];
    }

    if (isset($clientConfig[$entityKey]) && is_array($clientConfig[$entityKey])) {
      return $clientConfig[$entityKey];
    }

    return [];
  }

}