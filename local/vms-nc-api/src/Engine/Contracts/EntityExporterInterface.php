<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Contracts;

use Throwable;

interface EntityExporterInterface
{
  /**
   * Выгружает и форматирует массив данных или DTO для конкретной сущности
   *
   * @param array $clientConfig Конфиг клиента
   * @param array $industryConfig Конфиг индустрии
   * @param string $entityKey Ключ сущности (categories, products, attributes и т.д.)
   * @return array
   * @throws Throwable
   */
  public function export(array $clientConfig, array $industryConfig = [], string $entityKey = ''): array;

}