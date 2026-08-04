<?php

declare(strict_types=1);

namespace VmsNcApi\Repositories;

use Bitrix\Iblock\ElementPropertyTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\Iblock;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\SqlExpression;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use CUtil;
use RuntimeException;
use Throwable;
use VmsNcApi\Constants\AttributeType;
use VmsNcApi\Constants\CatalogType;
use VmsNcApi\Engine\Filters\CategoryFilter;
use VmsNcApi\Engine\Filters\OfferFilter;
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
      throw new RuntimeException('Модули iblock и catalog обязательны');
    }
    $this->hlRepo    = $hlRepo;
    $this->priceRepo = $priceRepo;
  }

  /**
   * Безопасное получение класса ORM-сущности инфоблока
   */
  private function getIblockEntityDataClass(int $iblockId): string
  {
    if ($iblockId <= 0) {
      throw new RuntimeException("Невалидный IBLOCK_ID: $iblockId");
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
      //
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
   * @throws ObjectPropertyException
   * @throws SystemException
   * @throws ArgumentException
   */
  public function getAttributes(array $clientConfig): array
  {
    $catalogIblockId = (int)($clientConfig['iblocks']['catalog'] ?? 0);
    $offersIblockId  = (int)($clientConfig['iblocks']['offers'] ?? 0);
    $iblocks         = array_filter([$catalogIblockId, $offersIblockId]);
    $propertyMap     = $clientConfig['property_map'] ?? [];

    $attributes = [];

    foreach ($propertyMap as $targetAttrCode => $mapConfig) {
      $sourcePropCode = (string)($mapConfig['source'] ?? '');
      $targetType     = (string)($mapConfig['type'] ?? AttributeType::STRING);
      $prefix         = (string)($mapConfig['prefix'] ?? 'opt_');

      $prop = PropertyTable::getList([
        'filter' => [
          '@IBLOCK_ID' => $iblocks,
          '=CODE'      => $sourcePropCode,
          '=ACTIVE'    => 'Y'
        ],
        'select' => ['ID', 'NAME', 'CODE', 'PROPERTY_TYPE', 'USER_TYPE', 'USER_TYPE_SETTINGS']
      ])->fetch();

      if (!$prop && empty($mapConfig['default'])) {
        continue;
      }

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
                'slug'          => $slug,
                'value'         => ['ru' => (string)($row['UF_NAME'] ?? $slug)],
                'meta'          => [
                  'hex'   => $hex,
                  'image' => $row['IMAGE_PATH'] ?? null
                ],
                'param'         => $slug
              ];
            }
          }
        }
      }

      $attrName = isset($mapConfig['name']) && is_array($mapConfig['name'])
        ? $mapConfig['name']
        : ['ru' => (string)($prop['NAME'] ?? $targetAttrCode)];

      $attributes[] = [
        'external_code'     => 'attr_' . $targetAttrCode,
        'code'              => $targetAttrCode,
        'type'              => $finalAttrType,
        'name'              => $attrName,
        'is_multiple'       => false,
        'options'           => $options,
        'option_param_type' => $finalAttrType === AttributeType::DICTIONARY ? 'string' : null
      ];
    }

    return $attributes;
  }

  /**
   * @throws ObjectPropertyException
   * @throws SystemException
   * @throws ArgumentException
   */
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

    // Выгружаем базовые товары
    /** @var ElementTable $catalogEntity */
    $catalogEntity = $this->getIblockEntityDataClass($catalogIblockId);
    $products = $catalogEntity::getList([
      'filter' => [
        '=IBLOCK_ID' => $catalogIblockId,
        '=ACTIVE'    => 'Y'
      ],
      'select' => ['ID', 'IBLOCK_SECTION_ID', 'NAME', 'CODE']
    ])->fetchAll();

    $productTypeRules = $clientConfig['product_type_rules'] ?? [];

    foreach ($products as $prod) {
      $secId = (int)$prod['IBLOCK_SECTION_ID'];
      if ($secId > 0 && !CategoryFilter::isSectionAllowed($secId, $categoryConfig)) {
        continue;
      }

      $prodId = (int)$prod['ID'];
      $code = !empty($prod['CODE']) ? strtolower((string)$prod['CODE']) : 'prod-' . $prodId;

      $productTypeExternalCode = ProductTypeResolver::resolve($prod, $secId, $productTypeRules);

      $priceData = $this->priceRepo->getProductPrice($prodId, $clientConfig);
      $rawCurr   = $priceData['currency'];
      $mappedCurr = $currencyMap[$rawCurr] ?? $rawCurr;

      $flatProducts[$prodId] = [
        'code'                       => $code,
        'external_code'              => 'prod_' . $code,
        'product_type_external_code' => $productTypeExternalCode,
        'category_external_code'     => $secId > 0 ? $catPrefix . $secId : null,
        'catalog_type'               => CatalogType::PRODUCT,
        'unit_code'                  => 'pcs',
        'slug'                       => $code,
        'name'                       => ['ru' => (string)$prod['NAME']],
        'preview_picture'            => null,
        'detail_picture'             => null,
        'eav'                        => $this->mapEavProperties($prodId, $catalogIblockId, $propertyMap, 'product', $offersIblockId),
        'is_active'                  => true,
        'is_variant'                 => false,
        'parent_code'                => null,
        'variant_data'               => null,
        'default_price_data'         => [
          'price'    => $priceData['price'],
          'currency' => $mappedCurr,
        ],
        'variants'                   => [],
      ];
    }

    // Выгружаем торговые предложения (SKU)
    if ($offersIblockId > 0) {
      /** @var ElementTable $offersEntity */
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

        $priceData = $this->priceRepo->getProductPrice($offId, $clientConfig);
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
          'eav'                       => $this->mapEavProperties($offId, $offersIblockId, $propertyMap, 'variant'),
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

    return StructureBuilder::build($flatProducts);
  }

  /**
   * Маппинг EAV-свойств элемента через D7 ORM без хардкода
   */
  private function mapEavProperties(
    int $elementId,
    int $iblockId,
    array $propertyMap,
    string $currentScope = 'product',
    int $offersIblockId = 0
  ): array
  {
    $eav = [];

    foreach ($propertyMap as $targetAttrCode => $mapConfig) {
      $scope = (string)($mapConfig['scope'] ?? 'both');

      if ($scope !== 'both' && $scope !== $currentScope) {
        continue;
      }

      $sourcePropCode = (string)($mapConfig['source'] ?? '');
      $type           = (string)($mapConfig['type'] ?? 'string');
      $prefix         = (string)($mapConfig['prefix'] ?? 'opt_');
      $transformers   = $mapConfig['transformers'] ?? [];
      $defaultValue   = $mapConfig['default'] ?? null;

      $rawValue = null;

      if ($sourcePropCode !== '') {
        $rawValue = $this->fetchRawPropertyValue($elementId, $iblockId, $sourcePropCode, $type);

        if (($rawValue === null || $rawValue === '') && $currentScope === 'product' && $offersIblockId > 0) {
          try {
            $firstOffer = ElementPropertyTable::getList([
              'filter' => [
                '=IBLOCK_PROPERTY_ID' => 29,
                '=VALUE' => $elementId
              ],
              'select' => ['IBLOCK_ELEMENT_ID'],
              'limit'  => 1
            ])->fetch();

            if ($firstOffer && !empty($firstOffer['IBLOCK_ELEMENT_ID'])) {
              $rawValue = $this->fetchRawPropertyValue((int)$firstOffer['IBLOCK_ELEMENT_ID'], $offersIblockId, $sourcePropCode, $type);
            }
          } catch (Throwable $e) {
            // Перехват исключения
          }
        }
      }

      if (($rawValue === null || $rawValue === '') && $defaultValue !== null) {
        $rawValue = $defaultValue;
      }

      if ($rawValue !== null && $rawValue !== '') {
        $finalValue = null;

        if (!empty($transformers)) {
          $finalValue = ValueTransformerPipeline::process($rawValue, $transformers);
        } elseif ($type === 'enum' || $type === 'hl') {
          $slug = $this->slugify((string)$rawValue);
          $finalValue = $prefix . $slug;
        } else {
          $finalValue = $rawValue;
        }

        if ($finalValue !== null && $finalValue !== '') {
          $eav[$targetAttrCode] = $finalValue;
        }
      }
    }

    return $eav;
  }

  private function fetchRawPropertyValue(int $elementId, int $iblockId, string $sourcePropCode, string $type): ?string
  {
    try {
      $propEntity = PropertyTable::getList([
        'filter' => ['=IBLOCK_ID' => $iblockId, '=CODE' => $sourcePropCode],
        'select' => ['ID', 'PROPERTY_TYPE']
      ])->fetch();

      if (!$propEntity) {
        return null;
      }

      $propId = (int)$propEntity['ID'];

      $valRes = ElementPropertyTable::getList([
        'filter' => ['=IBLOCK_ELEMENT_ID' => $elementId, '=IBLOCK_PROPERTY_ID' => $propId],
        'select' => ['VALUE']
      ])->fetch();

      if (!$valRes || empty($valRes['VALUE'])) {
        return null;
      }

      if ($type === 'enum' || $propEntity['PROPERTY_TYPE'] === 'L') {
        $enumRes = PropertyEnumerationTable::getList([
          'filter' => ['=ID' => (int)$valRes['VALUE']],
          'select' => ['VALUE', 'XML_ID'],
          'limit'  => 1
        ])->fetch();

        if ($enumRes) {
          return !empty($enumRes['XML_ID']) ? (string)$enumRes['XML_ID'] : (string)$enumRes['VALUE'];
        }

        return null;
      }

      return (string)$valRes['VALUE'];
    } catch (Throwable $e) {
      return null;
    }
  }

  private function slugify(string $text): string
  {
    if (class_exists(CUtil::class) && method_exists(CUtil::class, 'translit')) {
      $translit = CUtil::translit($text, 'ru', ['replace_space' => '_', 'replace_other' => '_']);
      return strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $translit), '_'));
    }

    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $text), '_'));
    return !empty($slug) ? $slug : 'item_' . md5($text);
  }

}