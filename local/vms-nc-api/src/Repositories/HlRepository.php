<?php

declare(strict_types=1);

namespace VmsNcApi\Repositories;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\FileTable;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use RuntimeException;

final class HlRepository
{
  /**
   * @throws LoaderException
   */
  public function __construct()
  {
    if (!Loader::includeModule('highloadblock')) {
      throw new RuntimeException('Модуль highloadblock не установлен');
    }
  }

  /**
   * Получить все записи HL-блока по имени таблицы
   *
   * @throws ObjectPropertyException
   * @throws SystemException
   * @throws ArgumentException
   */
  public function getTableData(string $tableName): array
  {
    $hlblock = HighloadBlockTable::getList([
      'filter' => ['=TABLE_NAME' => $tableName]
    ])->fetch();

    if (!$hlblock) {
      return [];
    }

    $entity = HighloadBlockTable::compileEntity($hlblock);
    $dataClass = $entity->getDataClass();

    $select = ['*'];
    $runtime = [];

    if ($entity->hasField('UF_FILE')) {
      $runtime[] = new ReferenceField(
        'FILE_REF',
        FileTable::class,
        ['=this.UF_FILE' => 'ref.ID']
      );
      $runtime[] = new ExpressionField(
        'IMAGE_PATH',
        'CONCAT("/upload/", CONCAT(%s, "/", %s))',
        ['FILE_REF.SUBDIR', 'FILE_REF.FILE_NAME']
      );
      $select[] = 'IMAGE_PATH';
    }

    return $dataClass::getList([
      'select'  => $select,
      'runtime' => $runtime
    ])->fetchAll();
  }

}