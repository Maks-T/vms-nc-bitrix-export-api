<?php

declare(strict_types=1);

namespace VmsNcApi\Repositories;

use Bitrix\Iblock\Iblock;
use Bitrix\Iblock\SectionTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Main\Loader;
use VmsNcApi\Engine\Filters\CategoryFilter;
use VmsNcApi\Engine\Filters\OfferFilter;
use VmsNcApi\Engine\ValueTransformerPipeline;
use RuntimeException;

final class IblockRepository
{
  /** @var HlRepository */
  private $hlRepo;

  public function __construct(HlRepository $hlRepo)
  {
    if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
      throw new RuntimeException('Модули iblock и catalog обязательны');
    }
    $this->hlRepo = $hlRepo;
  }

  /**
   * Выборка категорий с учетом фильтрации (whitelist / include_section_ids)
   */
  public function getCategories(array $clientConfig): array
  {
    $catalogIblockId = (int)($clientConfig['iblocks']['catalog'] ?? 0);
    $categoryConfig  = $clientConfig['category_filters'] ?? [];
    $prefix          = (string)($categoryConfig['external_code_prefix'] ?? 'cat_');

    $sections = SectionTable::getList([
      'filter' => [
        '=IBLOCK_ID' => $catalogIblockId,
        '=ACTIVE'    => 'Y'
      ],
      'select' => ['ID', 'IBLOCK_SECTION_ID', 'NAME', 'CODE', 'DEPTH_LEVEL', 'SORT'],
      'order'  => ['DEPTH_LEVEL' => 'ASC', 'SORT' => 'ASC']
    ])->fetchAll();

    $result = [];
    foreach ($sections as $sec) {
      $sectionId = (int)$sec['ID'];

      if (!CategoryFilter::isSectionAllowed($sectionId, $categoryConfig)) {
        continue;
      }

      $slug = !empty($sec['CODE']) ? (string)$sec['CODE'] : 'section-' . $sectionId;
      $parentCode = !empty($sec['IBLOCK_SECTION_ID'])
        ? $prefix . $sec['IBLOCK_SECTION_ID']
        : null;

      $result[] = [
        'external_code'        => $prefix . $sectionId,
        'parent_external_code' => $parentCode,
        'slug'                 => $slug,
        'name'                 => ['ru' => (string)$sec['NAME']]
      ];
    }

    return $result;
  }

  /**
   * Выборка атрибутов и заполнение их опций (Enum и Highload)
   */
  public function getAttributesWithDictionaries(array $clientConfig): array
  {
    $iblocks = array_values($clientConfig['iblocks'] ?? []);
    $propertyMap = $clientConfig['property_map'] ?? [];

    $attributes = [];

    foreach ($propertyMap as $targetAttrCode => $mapConfig) {
      $sourcePropCode = (string)($mapConfig['source'] ?? '');
      $type           = (string)($mapConfig['type'] ?? 'string');
      $prefix         = (string)($mapConfig['prefix'] ?? 'opt_');

      $prop = PropertyTable::getList([
        'filter' => [
          '=IBLOCK_ID' => $iblocks,
          '=CODE'      => $sourcePropCode,
          '=ACTIVE'    => 'Y'
        ],
        'select' => ['ID', 'NAME', 'CODE', 'PROPERTY_TYPE', 'USER_TYPE', 'USER_TYPE_SETTINGS']
      ])->fetch();

      if (!$prop) {
        continue;
      }

      $options = [];

      // 1. Списки (Enum)
      if ($type === 'enum' || $prop['PROPERTY_TYPE'] === 'L') {
        $enums = PropertyEnumerationTable::getList([
          'filter' => ['=PROPERTY_ID' => (int)$prop['ID']],
          'select' => ['ID', 'VALUE', 'XML_ID']
        ])->fetchAll();

        foreach ($enums as $enum) {
          $slug = !empty($enum['XML_ID']) ? strtolower((string)$enum['XML_ID']) : (string)$enum['ID'];
          $options[] = [
            'external_code' => $prefix . $slug,
            'slug'          => $slug,
            'value'         => ['ru' => (string)$enum['VALUE']],
            'meta'          => ['hex' => null, 'image' => null],
            'param'         => $slug
          ];
        }
      }

      // 2. Highload-блоки (HL)
      if ($type === 'hl' || $prop['USER_TYPE'] === 'directory') {
        $settings = unserialize((string)$prop['USER_TYPE_SETTINGS'], ['allowed_classes' => false]);
        $tableName = $settings['TABLE_NAME'] ?? '';

        if (!empty($tableName)) {
          $hlData = $this->hlRepo->getTableData($tableName);
          foreach ($hlData as $row) {
            $slug = strtolower((string)($row['UF_XML_ID'] ?? $row['ID']));
            $options[] = [
              'external_code' => $prefix . $slug,
              'slug'          => $slug,
              'value'         => ['ru' => (string)($row['UF_NAME'] ?? $slug)],
              'meta'          => [
                'hex'   => $row['UF_DEF'] ?? null,
                'image' => $row['IMAGE_PATH'] ?? null
              ],
              'param'         => $slug
            ];
          }
        }
      }

      $attributes[] = [
        'external_code'     => 'attr_' . $targetAttrCode,
        'code'              => $targetAttrCode,
        'type'              => ($type === 'enum' || $type === 'hl') ? 'dictionary' : $type,
        'name'              => ['ru' => (string)$prop['NAME']],
        'is_multiple'       => false,
        'options'           => $options,
        'option_param_type' => 'string'
      ];
    }

    return $attributes;
  }

  /**
   * Выборка товаров и торговых предложений (SKU)
   */
  public function getProducts(array $clientConfig): array
  {
    $catalogIblockId = (int)($clientConfig['iblocks']['catalog'] ?? 0);
    $offersIblockId  = (int)($clientConfig['iblocks']['offers'] ?? 0);
    $propertyMap     = $clientConfig['property_map'] ?? [];
    $offerFilters    = $clientConfig['offer_filters'] ?? [];
    $categoryConfig  = $clientConfig['category_filters'] ?? [];
    $catPrefix       = (string)($categoryConfig['external_code_prefix'] ?? 'cat_');

    $flatProducts = [];

    // 1. Выгружаем базовые товары
    $catalogEntity = Iblock::wakeUp($catalogIblockId)->getEntityDataClass();
    $products = $catalogEntity::getList([
      'filter' => ['=ACTIVE' => 'Y'],
      'select' => ['ID', 'IBLOCK_SECTION_ID', 'NAME', 'CODE']
    ])->fetchAll();

    foreach ($products as $prod) {
      $secId = (int)$prod['IBLOCK_SECTION_ID'];
      if ($secId > 0 && !CategoryFilter::isSectionAllowed($secId, $categoryConfig)) {
        continue;
      }

      $prodId = (int)$prod['ID'];
      $code = !empty($prod['CODE']) ? strtolower((string)$prod['CODE']) : 'prod-' . $prodId;

      $flatProducts[$prodId] = [
        'code'                       => $code,
        'external_code'              => 'prod_' . $code,
        'product_type_external_code' => 'type_acrylic_stone',
        'category_external_code'     => $secId > 0 ? $catPrefix . $secId : null,
        'catalog_type'               => 'product',
        'unit_code'                  => 'pcs',
        'slug'                       => $code,
        'name'                       => ['ru' => (string)$prod['NAME']],
        'preview_picture'            => null,
        'detail_picture'             => null,
        'eav'                        => $this->mapEavProperties($prodId, $catalogIblockId, $propertyMap),
        'is_active'                  => true,
        'is_variant'                 => false,
        'parent_code'                => null,
        'variant_data'               => null,
      ];
    }

    // 2. Выгружаем торговые предложения (SKU)
    if ($offersIblockId > 0) {
      $offersEntity = Iblock::wakeUp($offersIblockId)->getEntityDataClass();
      $offers = $offersEntity::getList([
        'filter' => ['=ACTIVE' => 'Y'],
        'select' => ['ID', 'NAME', 'CODE', 'PARENT_ID' => 'CML2_LINK.VALUE']
      ])->fetchAll();

      foreach ($offers as $off) {
        if (!OfferFilter::isOfferAllowed($off, $offerFilters)) {
          continue;
        }

        $offId = (int)$off['ID'];
        $parentId = (int)$off['PARENT_ID'];
        if (!isset($flatProducts[$parentId])) {
          continue;
        }

        $parentCode = $flatProducts[$parentId]['code'];
        $offCode = !empty($off['CODE']) ? strtolower((string)$off['CODE']) : 'sku-' . $offId;

        $variantData = [
          'external_code'             => 'sku_' . $offCode,
          'sku'                       => $offCode,
          'name'                      => null,
          'price_group_external_code' => 'pg_v0',
          'stock'                     => 10,
          'is_default'                => true,
          'preview_picture'           => null,
          'detail_picture'            => null,
          'eav'                       => $this->mapEavProperties($offId, $offersIblockId, $propertyMap),
          'is_manual_pricing'         => false,
          'cost_price'                => 295,
          'currency'                  => 'USD'
        ];

        $flatProducts['off_' . $offId] = [
          'code'         => $offCode,
          'is_variant'   => true,
          'parent_code'  => $parentCode,
          'variant_data' => $variantData
        ];
      }
    }

    return \VmsNcApi\Engine\StructureBuilder::build($flatProducts);
  }

  /**
   * Маппинг EAV-свойств элемента
   */
  private function mapEavProperties(int $elementId, int $iblockId, array $propertyMap): array
  {
    $eav = [];

    // Принудительно ставим группу раскроя #2 для камня
    $eav['cutting_groups'] = 'rec_cutting_groups_2';

    foreach ($propertyMap as $targetAttrCode => $mapConfig) {
      $sourcePropCode = (string)($mapConfig['source'] ?? '');
      $type           = (string)($mapConfig['type'] ?? 'string');
      $prefix         = (string)($mapConfig['prefix'] ?? 'opt_');
      $transformers   = $mapConfig['transformers'] ?? [];

      $propRes = PropertyEnumerationTable::getList([
        'filter' => [
          '=PROPERTY.IBLOCK_ID' => $iblockId,
          '=PROPERTY.CODE'      => $sourcePropCode
        ],
        'select' => ['VALUE', 'XML_ID']
      ])->fetch();

      $rawValue = $propRes ? ($propRes['XML_ID'] ?: $propRes['VALUE']) : null;

      if ($rawValue !== null && $rawValue !== '') {
        if (!empty($transformers)) {
          $eav[$targetAttrCode] = ValueTransformerPipeline::process($rawValue, $transformers);
        } elseif ($type === 'enum' || $type === 'hl') {
          $slug = strtolower((string)$rawValue);
          $eav[$targetAttrCode] = $prefix . $slug;
        } else {
          $eav[$targetAttrCode] = $rawValue;
        }
      }
    }

    return $eav;
  }
}