<?php

declare(strict_types=1);

use VmsNcApi\Constants\AttributeSettings;
use VmsNcApi\Constants\FilterMode;
use VmsNcApi\Engine\Exporters\DynamicCurrencyExporter;
use VmsNcApi\Engine\Exporters\IblockAttributeExporter;
use VmsNcApi\Engine\Exporters\IblockCategoryExporter;
use VmsNcApi\Engine\Exporters\IblockProductExporter;
use VmsNcApi\Engine\Exporters\PriceTypeExporter;
use VmsNcApi\Engine\Exporters\StaticIndustryExporter;
use VmsNcApi\Engine\Transformers\CastToInteger;
use VmsNcApi\Engine\Transformers\EnumToBoolean;
use VmsNcApi\Engine\Transformers\RegexExtractor;

return [

  /*
  |--------------------------------------------------------------------------
  | Идентификация клиента и локализация
  |--------------------------------------------------------------------------
  */
  'client_code' => 'stoleshka_ru',
  'industry'    => 'stone',
  'locales'     => ['ru'],

  /*
  |--------------------------------------------------------------------------
  | Инфоблоки и связи каталогов
  |--------------------------------------------------------------------------
  */
  'catalogs' => [
    ['catalog' => 2, 'offers' => 3],  // Инфоблок №1: Камень и слэбы
    ['catalog' => 9, 'offers' => 10], // Инфоблок №2: Раковины и сантехника
  ],

  /*
  |--------------------------------------------------------------------------
  | Экспортеры и ценообразование
  |--------------------------------------------------------------------------
  */
  'pricing' => [
    'retail_group_id' => 1,
    'cost_group_id'   => 2,
  ],

  'exporters' => [
    'currencies'           => DynamicCurrencyExporter::class,
    'price_types'          => PriceTypeExporter::class,
    'families'             => StaticIndustryExporter::class,
    'types'                => StaticIndustryExporter::class,
    'price_groups'         => StaticIndustryExporter::class,
    'complex_dictionaries' => StaticIndustryExporter::class,
    'categories'           => IblockCategoryExporter::class,
    'attributes'           => IblockAttributeExporter::class,
    'products'             => IblockProductExporter::class,
  ],

  // Правила конвертации псевдовалют Битрикса
  'currency_converter' => [
    'enabled' => true,
    'rules'   => [
      'STA' => ['target_currency' => 'USD', 'markup_percent' => 0.0],   // Staron (0%)
      'IDS' => ['target_currency' => 'USD', 'markup_percent' => 20.0],  // IDS (+20%)
      'IZO' => ['target_currency' => 'USD', 'markup_percent' => 20.0],  // IZO (+20%)
      'COR' => ['target_currency' => 'USD', 'markup_percent' => 100.0], // Corian (+100%)
      'BEL' => ['target_currency' => 'EUR', 'markup_percent' => 30.0],  // Belenco (+30%)
      'BLA' => ['target_currency' => 'EUR', 'markup_percent' => 40.0],  // Blanco (+40%)
    ],
  ],

  // Официальные ISO-валюты для Nicole Core
  'currencies' => [
    'RUB' => ['symbol' => '₽', 'name' => ['ru' => 'Российский рубль'], 'is_default' => true],
    'USD' => ['symbol' => '$', 'name' => ['ru' => 'Доллар США'],        'is_default' => false],
    'EUR' => ['symbol' => '€', 'name' => ['ru' => 'Евро'],              'is_default' => false],
  ],

  'currency_map' => [
    'IDS' => 'USD',
    'STA' => 'USD',
    'COR' => 'USD',
    'BEL' => 'EUR',
    'BLA' => 'EUR',
    'IZG' => 'RUB',
    'RUB' => 'RUB',
    'USD' => 'USD',
    'EUR' => 'EUR',
  ],

  'price_types' => [
    [
      'slug'          => 'retail',
      'currency_code' => 'RUB',
      'is_default'    => true,
      'name'          => ['ru' => 'Розничная цена'],
      'description'   => ['ru' => 'Базовая розничная цена в системе'],
    ],
  ],

  /*
  |--------------------------------------------------------------------------
  | Правила классификации типов товаров
  |--------------------------------------------------------------------------
  */
  'product_type_rules' => [
    'default' => 'type_acrylic_stone',
    'rules'   => [
      // Акриловый камень
      [
        'type'      => 'type_acrylic_stone',
        'condition' => 'section_id',
        'values'    => [
          38, 48, 49, 51, 52, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63,
          66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80,
          81, 82, 83, 84, 89, 91, 92, 93, 94, 95, 97, 99, 100, 101,
          102, 103, 104, 105, 126, 412, 413, 418, 419,
        ],
      ],

      // Кварцевый агломерат
      [
        'type'      => 'type_quartz_stone',
        'condition' => 'section_id',
        'values'    => [
          106, 109, 110, 111, 112, 113, 115, 116, 119, 120, 121, 122,
          123, 124, 125, 137, 370, 371, 379, 380, 381, 382, 390, 392,
          414, 415,
        ],
      ],

      // Кухонные мойки (Blanco)
      [
        'type'      => 'type_kitchen_sink',
        'condition' => 'section_id',
        'values'    => [394, 395, 396, 397],
      ],

      // Раковины и чаши для ванной (Kerrock, Staron)
      [
        'type'      => 'type_bathroom_sink',
        'condition' => 'section_id',
        'values'    => [315, 340, 341, 361, 364, 365, 378],
      ],

      // Фолбэк правила по ключевым словам
      [
        'type'      => 'type_quartz_stone',
        'condition' => 'regex',
        'field'     => 'NAME',
        'pattern'   => '/кварц|quartz|агломерат/ui',
      ],
      [
        'type'      => 'type_kitchen_sink',
        'condition' => 'regex',
        'field'     => 'NAME',
        'pattern'   => '/мойк|sink/ui',
      ],
      [
        'type'      => 'type_bathroom_sink',
        'condition' => 'regex',
        'field'     => 'NAME',
        'pattern'   => '/раковин|чаша|bowl/ui',
      ],
    ],
  ],

  /*
  |--------------------------------------------------------------------------
  | Маппинг текстовых описаний товара (анонс и подробно)
  |--------------------------------------------------------------------------
  */
  'description_map' => [
    'short_description' => ['sources' => ['PREVIEW_TEXT']],
    'description'       => ['sources' => ['DETAIL_TEXT']],
  ],

  /*
  |--------------------------------------------------------------------------
  | Фильтрация разделов (Categories)
  |--------------------------------------------------------------------------
  */
  'category_filters' => [
    'mode' => FilterMode::WHITELIST,

    'include_section_ids' => array_merge(
    // Инфоблок №2: Камень и слэбы
      [
        38, 48, 49, 51, 52, 54, 55, 56, 57, 58, 59, 60, 61, 62,
        63, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78,
        79, 80, 81, 82, 83, 84, 89, 91, 92, 93, 94, 95, 97, 99,
        100, 101, 102, 103, 104, 105, 106, 109, 110, 111, 112, 113,
        115, 116, 119, 120, 121, 122, 123, 124, 137, 370, 371, 379,
        380, 381, 382, 390, 392, 394, 395, 396, 397, 412, 413, 414,
        415, 418, 419
      ],

      // Инфоблок №9: Только раковины и чаши
      [
        340, 341, 361, 364, 365, 378
      ]
    ),

    'exclude_section_ids'  => [14, 99, 315, 406],
    'external_code_prefix' => 'cat_'
  ],

  /*
  |--------------------------------------------------------------------------
  | Фильтры базовых товаров и торговых предложений
  |--------------------------------------------------------------------------
  */
  'product_filters' => [
    'rules' => [
      // Исключаем товары со статусом "Снят с производства"
      [
        'property' => 'SNYAT',
        'operator' => 'not_empty',
      ],

      // Исключаем готовые изделия, кронштейны и подоконники по названиям
      [
        'field'    => 'NAME',
        'operator' => 'regex_exclude',
        'pattern'  => '/столешниц|кронштейн|подоконник|панель|тумба/ui',
      ],
    ],
  ],

  'offer_filters' => [
    'mode'  => FilterMode::BLACKLIST,
    'rules' => [
      [
        'field'    => 'ALL',
        'operator' => 'regex',
        'pattern'  => '/([012313][\/\\\\\s_,-]+[234]|0[.,_][57]|остаток|обрез|столешниц|мойк|раковин|под\s*ключ|изгот|услуг|необработан|без\s*обработк|половин|четверт|ostatok|obrez|stoleshnits|rakovin|izgot|uslug|neobrabotann)/ui',
      ],
    ],
  ],

  /*
  |--------------------------------------------------------------------------
  | Приоритеты и отбор вариаций (SKU)
  |--------------------------------------------------------------------------
  */
  'variant_priority_rules' => [
    'type_acrylic_stone' => [
      'mode'  => 'single_primary',
      'rules' => [
        ['attribute' => 'height', 'type' => 'match_order', 'values' => [12, 20, 6]],
        ['attribute' => 'length', 'type' => 'sort',        'order'  => 'desc'],
        ['attribute' => 'width',  'type' => 'sort',        'order'  => 'desc'],
      ],
    ],

    'type_quartz_stone' => [
      'mode'  => 'single_primary',
      'rules' => [
        ['attribute' => 'height', 'type' => 'match_order', 'values' => [20, 15, 30, 12]],
        ['attribute' => 'length', 'type' => 'sort',        'order'  => 'desc'],
        ['attribute' => 'width',  'type' => 'sort',        'order'  => 'desc'],
      ],
    ],

    'type_marble_stone' => [
      'mode'  => 'single_primary',
      'rules' => [
        ['attribute' => 'height', 'type' => 'match_order', 'values' => [20, 30]],
        ['attribute' => 'length', 'type' => 'sort',        'order'  => 'desc'],
        ['attribute' => 'width',  'type' => 'sort',        'order'  => 'desc'],
      ],
    ],
  ],

  /*
  |--------------------------------------------------------------------------
  | Маппинг EAV-атрибутов (с использованием лаконичных хелперов настроек)
  |--------------------------------------------------------------------------
  */
  'property_map' => [

    // Свойства базовых родительских товаров
    'brand' => [
      'source'   => 'BRAND_REF',
      'type'     => 'hl',
      'prefix'   => 'opt_brand_',
      'name'     => ['ru' => 'Бренд'],
      'scope'    => 'product',
      'settings' => AttributeSettings::checkbox(),
    ],

    'texture' => [
      'source'   => 'TEXTURA',
      'type'     => 'enum',
      'prefix'   => 'opt_texture_',
      'name'     => ['ru' => 'Текстура'],
      'scope'    => 'product',
      'settings' => AttributeSettings::checkbox(),
    ],

    'collection' => [
      'source'   => 'COLLECTION',
      'type'     => 'enum',
      'prefix'   => 'opt_collection_',
      'name'     => ['ru' => 'Коллекция'],
      'scope'    => 'product',
      'settings' => AttributeSettings::checkbox(),
    ],

    'material' => [
      'sources'  => ['MATERIAL', 'MAT_MOEK'],
      'type'     => 'enum',
      'prefix'   => 'opt_material_',
      'name'     => ['ru' => 'Материал'],
      'scope'    => 'product',
      'settings' => AttributeSettings::checkbox(),
    ],

    // Свойства вариаций SKU
    'color' => [
      'sources'  => ['COLOR', 'CVET_BLANCO'],
      'type'     => 'enum',
      'prefix'   => 'opt_color_',
      'name'     => ['ru' => 'Цвет'],
      'scope'    => 'variant',
      'settings' => AttributeSettings::color(),
    ],

    'length' => [
      'sources' => ['FORMAT_SLEB', 'RAZMER_LIST', 'CODE', 'NAME'],
      'default' => 3680,
      'type'    => 'numeric',
      'name'    => ['ru' => 'Длина (мм)'],
      'scope'   => 'variant',
      'transformers' => [
        [
          'class'   => RegexExtractor::class,
          'options' => [
            'pattern' => '/(?:(3680|3050|3200|3300|3500|3100|2760)(760|1440|1600|1500|1520|1650|2000))|(\d{3,4})[\*xх×kh\s_-]+(\d{3,4})/ui',
            'group'   => 1,
          ],
        ],
      ],
      'settings' => AttributeSettings::range(),
    ],

    'width' => [
      'sources' => ['FORMAT_SLEB', 'RAZMER_LIST', 'CODE', 'NAME'],
      'default' => 760,
      'type'    => 'numeric',
      'name'    => ['ru' => 'Ширина (мм)'],
      'scope'   => 'variant',
      'transformers' => [
        [
          'class'   => RegexExtractor::class,
          'options' => [
            'pattern' => '/(?:(3680|3050|3200|3300|3500|3100|2760)(760|1440|1600|1500|1520|1650|2000))|(\d{3,4})[\*xх×kh\s_-]+(\d{3,4})/ui',
            'group'   => 2,
          ],
        ],
      ],
      'settings' => AttributeSettings::range(),
    ],

    'height' => [
      'sources' => ['depth', 'FORMAT_SLEB', 'RAZMER_LIST', 'CODE', 'NAME'],
      'default' => 12,
      'type'    => 'numeric',
      'name'    => ['ru' => 'Толщина (мм)'],
      'scope'   => 'variant',
      'transformers' => [
        [
          'class'   => RegexExtractor::class,
          'options' => [
            'pattern' => '/(\d{3,4})[\*xх×kh\s_-]+(\d{3,4})(?:[\*xх×kh\s_-]+(\d{1,2}))?/ui',
            'group'   => 3,
          ],
        ],
        [
          'class' => CastToInteger::class,
        ],
      ],
      'settings' => AttributeSettings::range(),
    ],

    'supplier_article' => [
      'sources'  => ['ARTNUMBER', 'CODE'],
      'type'     => 'string',
      'name'     => ['ru' => 'Артикул поставщика'],
      'scope'    => 'variant',
      'settings' => AttributeSettings::hidden(),
    ],

  ],

];