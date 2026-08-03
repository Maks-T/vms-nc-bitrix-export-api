<?php

declare(strict_types=1);

namespace VmsNcApi\Repositories;

use Bitrix\Iblock\Iblock;
use Bitrix\Iblock\SectionTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\ElementPropertyTable;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\DB\SqlExpression;
use Bitrix\Main\Loader;
use VmsNcApi\Engine\Filters\CategoryFilter;
use VmsNcApi\Engine\Filters\OfferFilter;
use VmsNcApi\Engine\Transformers\ValueTransformerPipeline;
use RuntimeException;

final class IblockRepository
{
  /** @var HlRepository */
  private $hlRepo;

  /** @var CatalogPriceRepository */
  private $priceRepo;

  public function __construct(HlRepository $hlRepo, CatalogPriceRepository $priceRepo)
  {
    if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
      throw new RuntimeException('Модули iblock и catalog обязательны');
    }
    $this->hlRepo    = $hlRepo;
    $this->priceRepo = $priceRepo;
  }

  private function getIblockEntityDataClass(int $iblockId): string
  {
    if ($iblockId <= 0) {
      throw new RuntimeException("Невалидный IBLOCK_ID: {$iblockId}");
    }

    try {
      $iblock = Iblock::wakeUp($iblockId);
      if ($iblock) {
        $entityClass = $iblock->getEntityDataClass();
        if (!empty($entityClass) && class_exists($entityClass)) {
          return $entityClass;
        }
      }
    } catch (\Throwable $e) {
      // Фоллбэк
    }

    return ElementTable::class;
  }

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

      if ($type === 'enum' || $prop['PROPERTY_TYPE'] === 'L') {
        $enums = PropertyEnumerationTable::getList([
          'filter' => ['=PROPERTY_ID' => (int)$prop['ID']],
          'select' => ['ID', 'VALUE', 'XML_ID']
        ])->fetchAll();

        foreach ($enums as $enum) {
          $rawVal = !empty($enum['XML_ID']) ? (string)$enum['XML_ID'] : (string)$enum['VALUE'];
          $slug = $this->slugify($rawVal);

          $options[] = [
            'external_code' => $prefix . $slug,
            'slug'          => $slug,
            'value'         => ['ru' => (string)$enum['VALUE']],
            'meta'          => ['hex' => null, 'image' => null],
            'param'         => $slug
          ];
        }
      }

      if ($type === 'hl' || $prop['USER_TYPE'] === 'directory') {
        $settings = unserialize((string)$prop['USER_TYPE_SETTINGS'], ['allowed_classes' => false]);
        $tableName = $settings['TABLE_NAME'] ?? '';

        if (!empty($tableName)) {
          $hlData = $this->hlRepo->getTableData($tableName);
          foreach ($hlData as $row) {
            $rawVal = (string)($row['UF_XML_ID'] ?? $row['UF_NAME']);
            $slug = $this->slugify($rawVal);

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

  public function getProducts(array $clientConfig): array
  {
    $catalogIblockId = (int)($clientConfig['iblocks']['catalog'] ?? 0);
    $offersIblockId  = (int)($clientConfig['iblocks']['offers'] ?? 0);
    $propertyMap     = $clientConfig['property_map'] ?? [];
    $offerFilters    = $clientConfig['offer_filters'] ?? [];
    $categoryConfig  = $clientConfig['category_filters'] ?? [];
    $currencyMap     = $clientConfig['currency_map'] ?? [];
    $catPrefix       = (string)($categoryConfig['external_code_prefix'] ?? 'cat_');

    $flatProducts = [];

    // 1. Выгружаем базовые товары
    $catalogEntity = $this->getIblockEntityDataClass($catalogIblockId);
    $products = $catalogEntity::getList([
      'filter' => [
        '=IBLOCK_ID' => $catalogIblockId,
        '=ACTIVE'    => 'Y'
      ],
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
      $offersEntity = $this->getIblockEntityDataClass($offersIblockId);

      $select = ['ID', 'NAME', 'CODE'];
      $runtime = [];

      if ($offersEntity !== ElementTable::class) {
        $select['PARENT_ID'] = 'CML2_LINK.VALUE';
      } else {
        $runtime[] = new ReferenceField(
          'CML2_REF',
          ElementPropertyTable::class,
          [
            '=this.ID' => 'ref.IBLOCK_ELEMENT_ID',
            '=ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', 29)
          ]
        );
        $select['PARENT_ID'] = 'CML2_REF.VALUE';
      }

      $offers = $offersEntity::getList([
        'filter' => [
          '=IBLOCK_ID' => $offersIblockId,
          '=ACTIVE'    => 'Y'
        ],
        'select'  => $select,
        'runtime' => $runtime
      ])->fetchAll();

      // Массив счетчиков вариантов у каждого родителя
      $parentVariantCounts = [];

      foreach ($offers as $off) {
        if (!OfferFilter::isOfferAllowed($off, $offerFilters)) {
          continue; // Отсекает 1/2 и 1/4 слэбы через blacklist!
        }

        $offId = (int)$off['ID'];
        $parentId = (int)($off['PARENT_ID'] ?? 0);
        if (!isset($flatProducts[$parentId])) {
          continue;
        }

        $parentCode = $flatProducts[$parentId]['code'];
        $offCode = !empty($off['CODE']) ? strtolower((string)$off['CODE']) : 'sku-' . $offId;

        // Определяем первый вариант для флага is_default
        $variantIndex = $parentVariantCounts[$parentId] ?? 0;
        $parentVariantCounts[$parentId] = $variantIndex + 1;

        $priceData = $this->priceRepo->getProductPrice($offId);
        $rawCurr   = $priceData['currency'];
        $mappedCurr = $currencyMap[$rawCurr] ?? $rawCurr;

        $variantData = [
          'external_code'             => 'sku_' . $offCode,
          'sku'                       => $offCode,
          'name'                      => null,
          'price_group_external_code' => null,
          'stock'                     => 10,
          'is_default'                => ($variantIndex === 0),
          'preview_picture'           => null,
          'detail_picture'            => null,
          'eav'                       => $this->mapEavProperties($offId, $offersIblockId, $propertyMap),
          'is_manual_pricing'         => true,
          'cost_price'                => $priceData['price'],
          'currency'                  => $mappedCurr
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
   * Маппинг EAV-свойств элемента через D7 ORM
   */
  private function mapEavProperties(int $elementId, int $iblockId, array $propertyMap): array
  {
    $eav = [];
    $eav['cutting_groups'] = 'rec_cutting_groups_2';

    foreach ($propertyMap as $targetAttrCode => $mapConfig) {
      $sourcePropCode = (string)($mapConfig['source'] ?? '');
      $type           = (string)($mapConfig['type'] ?? 'string');
      $prefix         = (string)($mapConfig['prefix'] ?? 'opt_');
      $transformers   = $mapConfig['transformers'] ?? [];

      $rawValue = null;

      $propEntity = PropertyTable::getList([
        'filter' => ['=IBLOCK_ID' => $iblockId, '=CODE' => $sourcePropCode],
        'select' => ['ID', 'PROPERTY_TYPE', 'USER_TYPE']
      ])->fetch();

      if ($propEntity) {
        $propId = (int)$propEntity['ID'];

        if ($type === 'enum' || $propEntity['PROPERTY_TYPE'] === 'L') {
          $valRes = ElementPropertyTable::getList([
            'filter' => ['=IBLOCK_ELEMENT_ID' => $elementId, '=IBLOCK_PROPERTY_ID' => $propId],
            'select' => ['VALUE']
          ])->fetch();

          if ($valRes && !empty($valRes['VALUE'])) {
            $enumRes = PropertyEnumerationTable::getList([
              'filter' => ['=ID' => (int)$valRes['VALUE']],
              'select' => ['VALUE', 'XML_ID'],
              'limit'  => 1
            ])->fetch();

            if ($enumRes) {
              $rawValue = !empty($enumRes['XML_ID']) ? $enumRes['XML_ID'] : $enumRes['VALUE'];
            }
          }
        } else {
          $valRes = ElementPropertyTable::getList([
            'filter' => ['=IBLOCK_ELEMENT_ID' => $elementId, '=IBLOCK_PROPERTY_ID' => $propId],
            'select' => ['VALUE']
          ])->fetch();

          $rawValue = $valRes ? $valRes['VALUE'] : null;
        }
      }

      if ($rawValue !== null && $rawValue !== '') {
        if (!empty($transformers)) {
          $eav[$targetAttrCode] = ValueTransformerPipeline::process($rawValue, $transformers);
        } elseif ($type === 'enum' || $type === 'hl') {
          $slug = $this->slugify((string)$rawValue);
          $eav[$targetAttrCode] = $prefix . $slug;
        } else {
          $eav[$targetAttrCode] = $rawValue;
        }
      }
    }

    return $eav;
  }

  /**
   * Функция безопасного приведения строки к латинскому слагу
   */
  private function slugify(string $text): string
  {
    if (class_exists('\CUtil') && method_exists('\CUtil', 'translit')) {
      $translit = \CUtil::translit($text, 'ru', ['replace_space' => '_', 'replace_other' => '_']);
      return strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $translit), '_'));
    }

    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $text), '_'));
    return !empty($slug) ? $slug : 'item_' . md5($text);
  }
}