<?php

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
  'client_code' => 'stoleshka_ru',
  'industry'    => 'stone',

  'locales'     => ['ru'],

  // ИД ИНФОБЛОКОВ И ТИПОВ ЦЕН
  'iblocks' => [
    'catalog' => 2,
    'offers'  => 3,
  ],

  'pricing' => [
    'retail_group_id' => 1,
    'cost_group_id'   => 2,
  ],

  // 2. ДЕКЛАРАТИВНЫЕ ЭКСПОРТЕРЫ СУЩНОСТЕЙ
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


  // 2. ПРАВИЛА ОПРЕДЕЛЕНИЯ ТИПОВ ТОВАРОВ
  'product_type_rules' => [
    'default' => 'type_acrylic_stone',
    'rules'   => [
      // Все разделы Акрилового камня
      [
        'type'      => 'type_acrylic_stone',
        'condition' => 'section_id',
        'values'    => [38, 48, 49, 51, 52, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 89, 91, 92, 93, 94, 95, 97, 99, 100, 101, 102, 103, 104, 105, 126, 412, 413, 418, 419]
      ],

      // Все разделы Кварцевого агломерата
      [
        'type'      => 'type_quartz_stone',
        'condition' => 'section_id',
        'values'    => [106, 109, 110, 111, 112, 113, 115, 116, 119, 120, 121, 122, 123, 124, 125, 137, 370, 371, 379, 380, 381, 382, 390, 392, 414, 415]
      ],

      // Разделы Кухонных моек
      [
        'type'      => 'type_kitchen_sink',
        'condition' => 'section_id',
        'values'    => [394, 395, 396, 397]
      ],

      // Резервное правило поиска по словам
      [
        'type'      => 'type_quartz_stone',
        'condition' => 'regex',
        'field'     => 'NAME',
        'pattern'   => '/кварц|quartz|агломерат/ui'
      ],
      [
        'type'      => 'type_kitchen_sink',
        'condition' => 'regex',
        'field'     => 'NAME',
        'pattern'   => '/мойк|раковин|sink/ui'
      ]
    ]
  ],

  // ФИЛЬТРАЦИЯ КАТЕГОРИЙ
  'category_filters' => [
    'mode' => FilterMode::WHITELIST,
    'include_section_ids' => [
      38, 48, 49, 51, 52, 54, 55, 56,
      57, 58, 59, 60, 61, 62, 63, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 89, 91, 92, 93, 94, 95, 97, 99, 100, 101, 102, 103, 104, 105, 106, 109, 110, 111, 112, 113, 115, 116, 119, 120, 121, 122, 123, 124, 137, 370, 371, 379, 380, 381, 382, 390, 392, 394, 395, 396, 397, 412, 413, 414, 415, 418, 419
    ],
    'exclude_section_ids' => [14, 99, 406],
    'external_code_prefix' => 'cat_'
  ],

  // ФИЛЬТРАЦИЯ ТОРГОВЫХ ПРЕДЛОЖЕНИЙ
  'offer_filters' => [
    'mode' => FilterMode::BLACKLIST,
    'rules' => [
      [
        'field'    => 'NAME',
        'operator' => 'regex',
        'pattern'  => '/(1\/2|1\/4|3\/4|остаток|обрез)/ui',
      ]
    ]
  ],

  // МАППИНГ СВОЙСТВ
  'property_map' => [
    'brand' => [
      'source' => 'BRAND_REF',
      'type'   => 'hl',
      'prefix' => 'opt_brand_',
      'scope'  => 'product'
    ],
    'color' => [
      'source' => 'COLOR',
      'type'   => 'hl',
      'prefix' => 'opt_color_',
      'scope'  => 'product'
    ],
    'texture' => [
      'source' => 'TEXTURA',
      'type'   => 'enum',
      'prefix' => 'opt_texture_',
      'scope'  => 'product'
    ],
    'collection' => [
      'source' => 'COLLECTION',
      'type'   => 'enum',
      'prefix' => 'opt_collection_',
      'scope'  => 'product'
    ],
    'effect_akril' => [
      'source' => 'EFFECT_AKRIL',
      'type'   => 'enum',
      'prefix' => 'opt_prefix_',
      'scope'  => 'product'
    ],

    'is_bend' => [
      'source' => 'BEND_AKRIL',
      'type'   => 'enum',
      'scope'  => 'product',
      'transformers' => [
        [
          'class'   => EnumToBoolean::class,
          'options' => ['truthy' => ['yes_bend', 'yes', 'y', '1']]
        ]
      ]
    ],

    'length' => [
      'source' => 'FORMAT_SLEB',
      'type'   => 'numeric',
      'name'   => ['ru' => 'Длина (мм)'],
      'scope'  => 'product',
      'transformers' => [
        [
          'class'   => RegexExtractor::class,
          'options' => [
            'pattern' => '/(\d+)[\*xх×](\d+)[\*xх×](\d+)/ui',
            'group'   => 1
          ]
        ]
      ]
    ],
    'width' => [
      'source' => 'FORMAT_SLEB',
      'type'   => 'numeric',
      'name'   => ['ru' => 'Ширина (мм)'],
      'scope'  => 'product',
      'transformers' => [
        [
          'class'   => RegexExtractor::class,
          'options' => [
            'pattern' => '/(\d+)[\*xх×](\d+)[\*xх×](\d+)/ui',
            'group'   => 2
          ]
        ]
      ]
    ],
    'height' => [
      'source' => 'depth',
      'type'   => 'numeric',
      'name'   => ['ru' => 'Толщина (мм)'],
      'scope'  => 'product',
      'transformers' => [CastToInteger::class]
    ],
  ],

  // ВАЛЮТЫ И ЦЕНЫ
  'currencies' => [
    'RUB' => ['symbol' => '₽', 'name' => ['ru' => 'Российский рубль'], 'is_default' => true],
    'USD' => ['symbol' => '$', 'name' => ['ru' => 'Доллар США'],        'is_default' => false],
    'EUR' => ['symbol' => '€', 'name' => ['ru' => 'Евро'],              'is_default' => false],
  ],

  'currency_map' => [
    'IDS' => 'USD',
    'BEL' => 'EUR',
    'RUB' => 'RUB',
    'USD' => 'USD'
  ],

  'price_types' => [
    [
      'slug'          => 'retail',
      'currency_code' => 'RUB',
      'is_default'    => true,
      'name'          => ['ru' => 'Розничная цена'],
      'description'   => ['ru' => 'Базовая розничная цена в системе'],
    ]
  ]
];