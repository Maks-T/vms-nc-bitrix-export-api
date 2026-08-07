<?php

declare(strict_types=1);

namespace VmsNcApi\Repositories;

use Bitrix\Catalog\MeasureTable;
use Bitrix\Catalog\ProductTable;
use Bitrix\Iblock\ElementPropertyTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\Iblock;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\SqlExpression;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\FileTable;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use CUtil;
use RuntimeException;
use Throwable;
use VmsNcApi\Constants\AttributeType;
use VmsNcApi\Constants\CatalogType;
use VmsNcApi\Engine\Conditions\ConditionEvaluator;
use VmsNcApi\Engine\Filters\CategoryFilter;
use VmsNcApi\Engine\Filters\OfferFilter;
use VmsNcApi\Engine\Filters\ProductFilter;
use VmsNcApi\Engine\Resolvers\ProductTypeResolver;
use VmsNcApi\Engine\StructureBuilder;
use VmsNcApi\Engine\Transformers\ValueTransformerPipeline;

final class IblockRepository
{
  private HlRepository $hlRepo;
  private CatalogPriceRepository $priceRepo;

  /**
   * @throws LoaderException
   */
  public function __construct(HlRepository $hlRepo, CatalogPriceRepository $priceRepo)
  {
    if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
      throw new RuntimeException('модули iblock и catalog обязательны');
    }
    $this->hlRepo = $hlRepo;
    $this->priceRepo = $priceRepo;
  }

  private function getCatalogPairs(array $clientConfig): array
  {
    if (!empty($clientConfig['catalogs']) && is_array($clientConfig['catalogs'])) {
      return $clientConfig['catalogs'];
    }

    return [
      [
        'catalog' => (int)($clientConfig['iblocks']['catalog'] ?? 0),
        'offers' => (int)($clientConfig['iblocks']['offers'] ?? 0),
      ]
    ];
  }

  private function getIblockEntityDataClass(int $iblockId): string
  {
    if ($iblockId <= 0) {
      throw new RuntimeException("невалидный iblock_id: $iblockId");
    }

    try {
      $iblock = Iblock::wakeUp($iblockId);
      if ($iblock) {
        $entityClass = $iblock->getEntityDataClass();
        if (!empty($entityClass) && class_exists($entityClass)) {
          return $entityClass;
        }
      }
    } catch (Throwable $e) {
    }

    return ElementTable::class;
  }

  /**
   * @throws ObjectPropertyException
   * @throws SystemException
   * @throws ArgumentException
   */
  public function getCategories(array $clientConfig): array
  {
    $catalogPairs = $this->getCatalogPairs($clientConfig);
    $categoryConfig = $clientConfig['category_filters'] ?? [];
    $prefix = (string)($categoryConfig['external_code_prefix'] ?? 'cat_');

    $catalogIblockIds = array_column($catalogPairs, 'catalog');

    $sections = SectionTable::getList([
      'filter' => [
        '@IBLOCK_ID' => array_unique($catalogIblockIds),
        '=ACTIVE' => 'Y'
      ],
      'select' => ['ID', 'IBLOCK_SECTION_ID', 'NAME', 'CODE', 'DEPTH_LEVEL', 'SORT'],
      'order' => ['DEPTH_LEVEL' => 'ASC', 'SORT' => 'ASC']
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
        'external_code' => $prefix . $sectionId,
        'parent_external_code' => $parentCode,
        'slug' => $slug,
        'name' => ['ru' => (string)$sec['NAME']]
      ];
    }

    return $result;
  }

  /**
   * @throws ObjectPropertyException
   * @throws ArgumentException
   * @throws SystemException
   */
  public function getAttributes(array $clientConfig): array
  {
    $catalogPairs = $this->getCatalogPairs($clientConfig);
    $iblocks = [];
    foreach ($catalogPairs as $pair) {
      if (!empty($pair['catalog'])) $iblocks[] = (int)$pair['catalog'];
      if (!empty($pair['offers'])) $iblocks[] = (int)$pair['offers'];
    }

    $propertyMap = $clientConfig['property_map'] ?? [];
    $attributes = [];

    foreach ($propertyMap as $targetAttrCode => $mapConfig) {
      $sourcePropCode = '';
      if (!empty($mapConfig['sources']) && is_array($mapConfig['sources'])) {
        $sourcePropCode = (string)reset($mapConfig['sources']);
      } elseif (!empty($mapConfig['source'])) {
        $sourcePropCode = (string)$mapConfig['source'];
      }

      if ($sourcePropCode === '') {
        continue;
      }

      $targetType = (string)($mapConfig['type'] ?? AttributeType::STRING);
      $prefix = (string)($mapConfig['prefix'] ?? 'opt_');

      $prop = PropertyTable::getList([
        'filter' => [
          '@IBLOCK_ID' => array_unique($iblocks),
          '=CODE' => $sourcePropCode,
          '=ACTIVE' => 'Y'
        ],
        'select' => ['ID', 'NAME', 'CODE', 'PROPERTY_TYPE', 'USER_TYPE', 'USER_TYPE_SETTINGS']
      ])->fetch();

      switch ($targetType) {
        case 'enum':
        case 'hl':
          $finalAttrType = AttributeType::DICTIONARY;
          break;
        case 'numeric':
          $finalAttrType = AttributeType::NUMERIC;
          break;
        case 'boolean':
          $finalAttrType = AttributeType::BOOLEAN;
          break;
        case 'complex':
          $finalAttrType = AttributeType::COMPLEX;
          break;
        default:
          $finalAttrType = AttributeType::STRING;
          break;
      }

      $options = [];

      if ($finalAttrType === AttributeType::DICTIONARY && $prop) {
        if ($targetType === 'enum' || $prop['PROPERTY_TYPE'] === 'L') {
          $enums = PropertyEnumerationTable::getList([
            'filter' => ['=PROPERTY_ID' => (int)$prop['ID']],
            'select' => ['ID', 'VALUE', 'XML_ID']
          ])->fetchAll();

          foreach ($enums as $enum) {
            $slug = $this->slugify((string)$enum['VALUE']);

            $options[] = [
              'external_code' => $prefix . $slug,
              'slug' => $slug,
              'value' => ['ru' => (string)$enum['VALUE']],
              'meta' => ['hex' => null, 'image' => null],
              'param' => $slug
            ];
          }
        }

        if ($targetType === 'hl' || $prop['USER_TYPE'] === 'directory') {
          $settings = unserialize((string)$prop['USER_TYPE_SETTINGS'], ['allowed_classes' => false]);
          $tableName = $settings['TABLE_NAME'] ?? '';

          if (!empty($tableName)) {
            $hlData = $this->hlRepo->getTableData($tableName);
            foreach ($hlData as $row) {
              $rawVal = (string)($row['UF_XML_ID'] ?? $row['UF_NAME']);
              $slug = $this->slugify($rawVal);

              $hexRaw = isset($row['UF_DEF']) ? trim((string)$row['UF_DEF']) : '';
              $hex = (!empty($hexRaw) && $hexRaw !== '0' && $hexRaw !== '0.0' && strpos($hexRaw, '#') === 0) ? $hexRaw : null;

              $options[] = [
                'external_code' => $prefix . $slug,
                'slug' => $slug,
                'value' => ['ru' => (string)($row['UF_NAME'] ?? $slug)],
                'meta' => [
                  'hex' => $hex,
                  'image' => $row['IMAGE_PATH'] ?? null
                ],
                'param' => $slug
              ];
            }
          }
        }
      }

      $attrName = isset($mapConfig['name']) && is_array($mapConfig['name'])
        ? $mapConfig['name']
        : ['ru' => (string)($prop['NAME'] ?? $targetAttrCode)];

      switch ($targetAttrCode) {
        case 'color':
          $defaultFilterType = 'color';
          break;
        case 'length':
        case 'width':
        case 'height':
          $defaultFilterType = 'range';
          break;
        default:
          $defaultFilterType = 'checkbox';
          break;
      }

      $attrSettings = $mapConfig['settings'] ?? [
        'channels' => [
          'widget' => [
            'is_public' => true,
            'is_settings_public' => true,
            'is_filterable' => true,
            'is_collapsed' => false,
            'filter_type' => $defaultFilterType,
          ],
          'catalog' => [
            'is_public' => true,
            'is_settings_public' => true,
            'is_filterable' => true,
            'is_collapsed' => false,
            'filter_type' => $defaultFilterType,
          ]
        ]
      ];

      $attributes[] = [
        'external_code' => 'attr_' . $targetAttrCode,
        'code' => $targetAttrCode,
        'type' => $finalAttrType,
        'name' => $attrName,
        'is_multiple' => false,
        'settings' => $attrSettings,
        'options' => $options,
        'option_param_type' => $finalAttrType === AttributeType::DICTIONARY ? 'string' : null
      ];
    }

    return $attributes;
  }

  /**
   * @throws ObjectPropertyException
   * @throws ArgumentException
   * @throws SystemException
   * @throws LoaderException
   */
  public function getProducts(array $clientConfig): array
  {
    $catalogPairs = $this->getCatalogPairs($clientConfig);
    $propertyMap = $clientConfig['property_map'] ?? [];
    $offerFilters = $clientConfig['offer_filters'] ?? [];
    $productFilters = $clientConfig['product_filters'] ?? [];
    $categoryConfig = $clientConfig['category_filters'] ?? [];
    $catPrefix = (string)($categoryConfig['external_code_prefix'] ?? 'cat_');

    $allCatalogIds = array_column($catalogPairs, 'catalog');
    $allOffersIds = array_column($catalogPairs, 'offers');
    $allIblockIds = array_unique(array_filter(array_merge($allCatalogIds, $allOffersIds)));

    $propMetaMap = $this->fetchPropertyMetaMap($allIblockIds, $propertyMap, $productFilters);
    $enumMap = $this->fetchEnumMap($propMetaMap);

    $products = [];
    $offers = [];

    foreach ($catalogPairs as $pair) {
      $catId = (int)($pair['catalog'] ?? 0);
      $offId = (int)($pair['offers'] ?? 0);

      if ($catId > 0) {
        /** @var ElementTable $catalogEntity */
        $catalogEntity = $this->getIblockEntityDataClass($catId);
        $fetchedProds = $catalogEntity::getList([
          'filter' => [
            '=IBLOCK_ID' => $catId,
            '=ACTIVE' => 'Y'
          ],
          'select' => ['ID', 'IBLOCK_SECTION_ID', 'NAME', 'CODE', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'PREVIEW_TEXT', 'DETAIL_TEXT']
        ])->fetchAll();

        foreach ($fetchedProds as $p) {
          $products[] = $p;
        }
      }

      if ($offId > 0) {
        /** @var ElementTable|string $offersEntity */
        $offersEntity = $this->getIblockEntityDataClass($offId);
        $select = ['ID', 'NAME', 'CODE', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'];
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

        $fetchedOffers = $offersEntity::getList([
          'filter' => [
            '=IBLOCK_ID' => $offId,
            '=ACTIVE' => 'Y'
          ],
          'select' => $select,
          'runtime' => $runtime
        ])->fetchAll();

        foreach ($fetchedOffers as $o) {
          $offers[] = $o;
        }
      }
    }

    $allElementIds = array_merge(
      array_column($products, 'ID'),
      array_column($offers, 'ID')
    );

    if (empty($allElementIds)) {
      return [];
    }

    $fileIds = [];
    foreach ($products as $p) {
      if (!empty($p['PREVIEW_PICTURE'])) $fileIds[] = (int)$p['PREVIEW_PICTURE'];
      if (!empty($p['DETAIL_PICTURE'])) $fileIds[] = (int)$p['DETAIL_PICTURE'];
    }
    foreach ($offers as $o) {
      if (!empty($o['PREVIEW_PICTURE'])) $fileIds[] = (int)$o['PREVIEW_PICTURE'];
      if (!empty($o['DETAIL_PICTURE'])) $fileIds[] = (int)$o['DETAIL_PICTURE'];
    }

    $filePathsMap = $this->fetchFilePathsBatch($fileIds);
    $pricesBatchMap = $this->priceRepo->getPricesBatch($allElementIds, $clientConfig);
    $eavValuesBatchMap = $this->fetchEavValuesBatchMap($allElementIds, $propMetaMap, $enumMap);
    $unitsBatchMap = $this->fetchProductUnitsBatch(array_column($products, 'ID'));

    $flatProducts = [];
    $prodPreviewMap = [];
    $productTypeRules = $clientConfig['product_type_rules'] ?? [];

    $queryParams = $clientConfig['query_params'] ?? [];
    $page = !empty($queryParams['page']) ? (int)$queryParams['page'] : null;
    $limit = !empty($queryParams['limit']) ? (int)$queryParams['limit'] : (!empty($queryParams['per_page']) ? (int)$queryParams['per_page'] : null);

    $defaultCurrency = 'RUB';
    foreach ($clientConfig['currencies'] ?? [] as $currCode => $currData) {
      if (!empty($currData['is_default'])) {
        $defaultCurrency = (string)$currCode;
        break;
      }
    }

    $defaultPriceData = [
      'cost_price' => 0.0,
      'currency' => $defaultCurrency,
      'markup_percent' => 0.0,
    ];

    foreach ($products as $prod) {
      $prodId = (int)$prod['ID'];
      $secId = (int)$prod['IBLOCK_SECTION_ID'];

      $productTypeExternalCode = ProductTypeResolver::resolve($prod, $secId, $productTypeRules);

      if (!ProductFilter::isProductAllowed($prod, $secId, $productTypeExternalCode, $eavValuesBatchMap[$prodId] ?? [], $clientConfig)) {
        continue;
      }

      $code = !empty($prod['CODE']) ? strtolower((string)$prod['CODE']) : 'prod-' . $prodId;
      $priceData = $pricesBatchMap[$prodId] ?? $defaultPriceData;

      $eavResult = $this->mapEavFromBatch('product', $propertyMap, $eavValuesBatchMap[$prodId] ?? [], $prod, $productTypeExternalCode);
      $prodEav = $eavResult['output'];
      $calculatedEav = $eavResult['calculated'];

      $prodPreviewId = !empty($prod['PREVIEW_PICTURE'])
        ? (int)$prod['PREVIEW_PICTURE']
        : (!empty($prod['DETAIL_PICTURE']) ? (int)$prod['DETAIL_PICTURE'] : 0);

      $prodPreviewPath = $prodPreviewId > 0 ? ($filePathsMap[$prodPreviewId] ?? null) : null;
      $prodPreviewMap[$prodId] = $prodPreviewPath;

      $previewText = !empty($prod['PREVIEW_TEXT']) ? trim((string)$prod['PREVIEW_TEXT']) : null;
      $detailText = !empty($prod['DETAIL_TEXT']) ? trim((string)$prod['DETAIL_TEXT']) : null;

      $unitCode = $unitsBatchMap[$prodId] ?? 'pcs';

      $flatProducts[$prodId] = [
        'code' => $code,
        'external_code' => 'prod_' . $code,
        'product_type_external_code' => $productTypeExternalCode,
        'category_external_code' => $secId > 0 ? $catPrefix . $secId : null,
        'catalog_type' => CatalogType::PRODUCT,
        'unit_code' => $unitCode,
        'slug' => $code,
        'name' => ['ru' => (string)$prod['NAME']],
        'short_description' => $previewText !== null ? ['ru' => $previewText] : null,
        'description' => $detailText !== null ? ['ru' => $detailText] : null,
        'preview_picture' => $prodPreviewPath,
        'detail_picture' => null,
        'eav' => $prodEav,
        'calculated_eav' => $calculatedEav,
        'is_active' => true,
        'is_variant' => false,
        'parent_code' => null,
        'variant_data' => null,
        'default_price_data' => [
          'price' => $priceData['cost_price'],
          'currency' => $priceData['currency'],
        ],
        'variants' => [],
      ];
    }


    // Обработка торговых предложений (sku / вариаций)
    $parentVariantCounts = [];
    foreach ($offers as $off) {
      if (!OfferFilter::isOfferAllowed($off, $offerFilters)) {
        continue;
      }

      $offId = (int)$off['ID'];
      $parentId = (int)($off['PARENT_ID'] ?? 0);

      if (!isset($flatProducts[$parentId])) {
        continue;
      }

      $parentCode = $flatProducts[$parentId]['code'];
      $offCode = !empty($off['CODE']) ? strtolower((string)$off['CODE']) : 'sku-' . $offId;

      $variantIndex = $parentVariantCounts[$parentId] ?? 0;
      $parentVariantCounts[$parentId] = $variantIndex + 1;

      $priceData = $pricesBatchMap[$offId] ?? $defaultPriceData;

      $parentProductType = $flatProducts[$parentId]['product_type_external_code'] ?? '';
      $parentCalculatedEav = $flatProducts[$parentId]['calculated_eav'] ?? ($flatProducts[$parentId]['eav'] ?? []);

      $variantEavResult = $this->mapEavFromBatch('variant', $propertyMap, $eavValuesBatchMap[$offId] ?? [], $off, $parentProductType, $parentCalculatedEav);
      $eav = $variantEavResult['output'];

      $offPreviewId = !empty($off['PREVIEW_PICTURE'])
        ? (int)$off['PREVIEW_PICTURE']
        : (!empty($off['DETAIL_PICTURE']) ? (int)$off['DETAIL_PICTURE'] : 0);

      $offPreviewPath = $offPreviewId > 0 ? ($filePathsMap[$offPreviewId] ?? null) : null;

      if ($offPreviewPath === null) {
        $offPreviewPath = $prodPreviewMap[$parentId] ?? null;
      }

      $variantData = [
        'external_code' => 'sku_' . $offCode,
        'sku' => $offCode,
        'name' => null,
        'price_group_external_code' => null,
        'stock' => 10,
        'is_default' => ($variantIndex === 0),
        'preview_picture' => $offPreviewPath,
        'detail_picture' => null,
        'eav' => $eav,
        'is_manual_pricing' => true,
        'cost_price' => $priceData['cost_price'],
        'currency' => $priceData['currency'],
        'markup_percent' => $priceData['markup_percent'],
      ];

      $flatProducts['off_' . $offId] = [
        'code' => $offCode,
        'is_variant' => true,
        'parent_code' => $parentCode,
        'variant_data' => $variantData
      ];
    }

    if ($page !== null && $limit !== null && $limit > 0) {
      $offset = ($page - 1) * $limit;
      $flatProducts = array_slice($flatProducts, $offset, $limit, true);
    }

    return StructureBuilder::build($flatProducts, $clientConfig);
  }

  /**
   * @throws LoaderException
   */
  private function fetchProductUnitsBatch(array $productIds): array
  {
    if (empty($productIds) || !Loader::includeModule('catalog')) {
      return [];
    }

    try {
      $rows = ProductTable::getList([
        'filter' => ['@ID' => $productIds],
        'select' => [
          'ID',
          'MEASURE_CODE' => 'MEASURE_REF.CODE',
          'MEASURE_SYMBOL' => 'MEASURE_REF.SYMBOL_RUS'
        ],
        'runtime' => [
          new ReferenceField(
            'MEASURE_REF',
            MeasureTable::class,
            ['=this.MEASURE' => 'ref.ID']
          )
        ]
      ])->fetchAll();

      $map = [];
      foreach ($rows as $r) {
        $prodId = (int)$r['ID'];
        $code = (string)($r['MEASURE_CODE'] ?? '');
        $symbol = mb_strtolower(trim((string)($r['MEASURE_SYMBOL'] ?? '')));

        $unitSlug = 'pcs';

        if ($code === '055' || strpos($symbol, 'м2') !== false || strpos($symbol, 'кв') !== false) {
          $unitSlug = 'm2';
        } elseif ($code === '018' || strpos($symbol, 'пог') !== false || strpos($symbol, 'м.п') !== false) {
          $unitSlug = 'm';
        } elseif ($code === 'E48' || strpos($symbol, 'усл') !== false) {
          $unitSlug = 'srv';
        } elseif ($code === '671' || strpos($symbol, 'компл') !== false) {
          $unitSlug = 'set';
        }

        $map[$prodId] = $unitSlug;
      }

      return $map;
    } catch (Throwable $e) {
      return [];
    }
  }

  /**
   * @throws ObjectPropertyException
   * @throws SystemException
   * @throws ArgumentException
   */
  private function fetchFilePathsBatch(array $fileIds): array
  {
    $fileIds = array_filter(array_map('intval', array_unique($fileIds)));
    if (empty($fileIds)) {
      return [];
    }

    $files = FileTable::getList([
      'filter' => ['@ID' => $fileIds],
      'select' => ['ID', 'SUBDIR', 'FILE_NAME']
    ])->fetchAll();

    $map = [];
    foreach ($files as $f) {
      $map[(int)$f['ID']] = '/upload/' . $f['SUBDIR'] . '/' . $f['FILE_NAME'];
    }

    return $map;
  }

  /**
   * @throws ObjectPropertyException
   * @throws SystemException
   * @throws ArgumentException
   */
  private function fetchPropertyMetaMap(array $iblocks, array $propertyMap, array $productFilters = []): array
  {
    $codes = [];
    foreach ($propertyMap as $cfg) {
      if (!empty($cfg['sources']) && is_array($cfg['sources'])) {
        foreach ($cfg['sources'] as $src) {
          $codes[] = (string)$src;
        }
      } elseif (!empty($cfg['source'])) {
        $codes[] = (string)$cfg['source'];
      }

      if (!empty($cfg['fallback'])) {
        $codes[] = (string)$cfg['fallback'];
      }
    }

    foreach ($productFilters['rules'] ?? [] as $rule) {
      if (!empty($rule['property'])) {
        $codes[] = (string)$rule['property'];
      }
    }

    if (empty($codes) || empty($iblocks)) {
      return [];
    }

    $props = PropertyTable::getList([
      'filter' => ['@IBLOCK_ID' => $iblocks, '@CODE' => array_unique($codes), '=ACTIVE' => 'Y'],
      'select' => ['ID', 'CODE', 'PROPERTY_TYPE', 'USER_TYPE']
    ])->fetchAll();

    $map = [];
    foreach ($props as $p) {
      $map[$p['CODE']] = [
        'id' => (int)$p['ID'],
        'type' => $p['PROPERTY_TYPE'],
        'user_type' => $p['USER_TYPE']
      ];
    }

    return $map;
  }

  /**
   * @throws ObjectPropertyException
   * @throws SystemException
   * @throws ArgumentException
   */
  private function fetchEnumMap(array $propMetaMap): array
  {
    $enumPropIds = [];
    foreach ($propMetaMap as $p) {
      if ($p['type'] === 'L') {
        $enumPropIds[] = $p['id'];
      }
    }

    if (empty($enumPropIds)) {
      return [];
    }

    $enums = PropertyEnumerationTable::getList([
      'filter' => ['@PROPERTY_ID' => $enumPropIds],
      'select' => ['ID', 'VALUE', 'XML_ID']
    ])->fetchAll();

    $map = [];
    foreach ($enums as $e) {
      $map[(int)$e['ID']] = (string)$e['VALUE'];
    }

    return $map;
  }

  /**
   * @throws ObjectPropertyException
   * @throws SystemException
   * @throws ArgumentException
   */
  private function fetchEavValuesBatchMap(array $elementIds, array $propMetaMap, array $enumMap): array
  {
    if (empty($elementIds) || empty($propMetaMap)) {
      return [];
    }

    $propIdToCode = [];
    foreach ($propMetaMap as $code => $meta) {
      $propIdToCode[$meta['id']] = $code;
    }

    $propValues = ElementPropertyTable::getList([
      'filter' => [
        '@IBLOCK_ELEMENT_ID' => $elementIds,
        '@IBLOCK_PROPERTY_ID' => array_keys($propIdToCode)
      ],
      'select' => ['IBLOCK_ELEMENT_ID', 'IBLOCK_PROPERTY_ID', 'VALUE']
    ])->fetchAll();

    $result = [];
    foreach ($propValues as $pv) {
      $elemId = (int)$pv['IBLOCK_ELEMENT_ID'];
      $propId = (int)$pv['IBLOCK_PROPERTY_ID'];
      $code = $propIdToCode[$propId] ?? null;
      $val = (string)$pv['VALUE'];

      if (!$code || $val === '') {
        continue;
      }

      if (isset($enumMap[(int)$val])) {
        $val = $enumMap[(int)$val];
      }

      $result[$elemId][$code] = $val;
    }

    return $result;
  }

  private function mapEavFromBatch(
    string $currentScope,
    array  $propertyMap,
    array  $elementValues,
    array  $rawElementData = [],
    string $productTypeCode = '',
    array  $parentEav = []
  ): array {
    $outputEav = [];
    $calculatedEav = [];

    $context = [
      'product_type'      => $productTypeCode,
      'product_eav'       => $currentScope === 'product' ? $calculatedEav : $parentEav,
      'variant_eav'       => $currentScope === 'variant' ? $calculatedEav : [],
      'product'           => $currentScope === 'product' ? $rawElementData : ($rawElementData['parent_product'] ?? []),
      'variant'           => $currentScope === 'variant' ? $rawElementData : [],
      'bitrix_element'    => $rawElementData,
      'bitrix_properties' => $elementValues,
    ];

    foreach ($propertyMap as $targetAttrCode => $mapConfig) {
      $scope   = (string)($mapConfig['scope'] ?? 'both');
      $inherit = !isset($mapConfig['inherit']) || (bool)$mapConfig['inherit'];

      $type          = (string)($mapConfig['type'] ?? 'string');
      $prefix        = (string)($mapConfig['prefix'] ?? 'opt_');
      $transformers  = $mapConfig['transformers'] ?? [];
      $defaultConfig = $mapConfig['default'] ?? null;

      $sourcesList = [];
      if (!empty($mapConfig['sources']) && is_array($mapConfig['sources'])) {
        $sourcesList = $mapConfig['sources'];
      } else {
        if (!empty($mapConfig['source'])) {
          $sourcesList[] = (string)$mapConfig['source'];
        }
        if (!empty($mapConfig['fallback'])) {
          $sourcesList[] = (string)$mapConfig['fallback'];
        }
      }

      $finalValue = null;

      foreach ($sourcesList as $sourceCode) {
        $val = $elementValues[$sourceCode] ?? ($rawElementData[$sourceCode] ?? null);
        if ($val === null || $val === '' || $val === '0' || $val === 0) {
          continue;
        }

        if (!empty($transformers)) {
          $transformed = ValueTransformerPipeline::process($val, $transformers);
          if ($transformed !== null && $transformed !== '') {
            $finalValue = $transformed;
            break;
          }
        } elseif ($type === 'enum' || $type === 'hl') {
          $slug = $this->slugify((string)$val);
          if ($slug !== '') {
            $finalValue = $prefix . $slug;
            break;
          }
        } else {
          $finalValue = $val;
          break;
        }
      }

      if (($finalValue === null || $finalValue === '') && $currentScope === 'variant' && $inherit) {
        if (isset($parentEav[$targetAttrCode]) && $parentEav[$targetAttrCode] !== null && $parentEav[$targetAttrCode] !== '') {
          $finalValue = $parentEav[$targetAttrCode];
        }
      }

      if (($finalValue === null || $finalValue === '') && $defaultConfig !== null) {
        if ($currentScope === 'product') {
          $context['product_eav'] = $calculatedEav;
        } else {
          $context['variant_eav'] = $calculatedEav;
        }

        $finalValue = $this->resolveDefaultValue($defaultConfig, $context);
      }

      if ($finalValue !== null && $finalValue !== '') {
        $calculatedEav[$targetAttrCode] = $finalValue;
      }

      $shouldAttach = ($scope === 'both') || ($scope === $currentScope);

      if ($shouldAttach && $finalValue !== null && $finalValue !== '') {
        $outputEav[$targetAttrCode] = $finalValue;
      }
    }

    return [
      'output'     => $outputEav,
      'calculated' => $calculatedEav,
    ];
  }

  private function resolveDefaultValue($defaultConfig, array $context)
  {
    if ($defaultConfig === null) {
      return null;
    }

    if (!is_array($defaultConfig)) {
      return $defaultConfig;
    }

    $rules = $defaultConfig['rules'] ?? [];

    foreach ($rules as $rule) {
      if (ConditionEvaluator::matches($rule, $context)) {
        return $rule['value'] ?? ($rule['then'] ?? null);
      }
    }

    return $defaultConfig['fallback'] ?? null;
  }

  private function slugify(string $text): string
  {
    $text = trim($text);
    if ($text === '') {
      return 'item_' . md5(uniqid('', true));
    }

    $matrix = [
      'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh',
      'з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
      'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts',
      'ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu',
      'я'=>'ya',
      'А'=>'a','Б'=>'b','В'=>'v','Г'=>'g','Д'=>'d','Е'=>'e','Ё'=>'e','Ж'=>'zh',
      'З'=>'z','И'=>'i','Й'=>'y','К'=>'k','Л'=>'l','М'=>'m','Н'=>'n','О'=>'o',
      'П'=>'p','Р'=>'r','С'=>'s','Т'=>'t','У'=>'u','Ф'=>'f','Х'=>'kh','Ц'=>'ts',
      'Ч'=>'ch','Ш'=>'sh','Щ'=>'shch','Ъ'=>'','Ы'=>'y','Ь'=>'','Э'=>'e','Ю'=>'yu',
      'Я'=>'ya'
    ];

    $str = strtr($text, $matrix);
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $str), '_'));
    $slug = preg_replace('/_+/', '_', $slug);

    return !empty($slug) ? $slug : 'item_' . md5($text);
  }

}