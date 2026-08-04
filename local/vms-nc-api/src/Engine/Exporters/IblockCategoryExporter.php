<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Exporters;

use VmsNcApi\Engine\Contracts\EntityExporterInterface;
use VmsNcApi\Repositories\IblockRepository;

final class IblockCategoryExporter implements EntityExporterInterface
{
  private IblockRepository $iblockRepo;

  public function __construct(IblockRepository $iblockRepo)
  {
    $this->iblockRepo = $iblockRepo;
  }

  /**
   * @inheritDoc
   */
  public function export(array $clientConfig, array $industryConfig = [], string $entityKey = ''): array
  {
    return $this->iblockRepo->getCategories($clientConfig);
  }

}