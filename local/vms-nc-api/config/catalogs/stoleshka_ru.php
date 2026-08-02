<?php

return [
  'client_code' => 'stoleshka_ru',
  'industry'    => 'stone',

  // 1. ИД ИНФОБЛОКОВ
  'iblocks' => [
    'catalog' => 2, // Инфоблок "Камень"
    'offers'  => 3, // Торговые предложения
  ],

  // 2. ФИЛЬТРАЦИЯ КАТЕГОРИЙ
  'category_filters' => [
    'mode' => 'whitelist',
    'include_section_ids' => [16, 17, 18, 19, 20, 23, 24, 25, 26, 37, 38, 39, 43, 44, 45, 46, 47, 48, 49],
    'exclude_section_ids' => [14, 99],
    'external_code_prefix' => 'cat_'
  ],

  // 3. ФИЛЬТРАЦИЯ ТОРГОВЫХ ПРЕДЛОЖЕНИЙ (Оставляем только целые листы)
  'offer_filters' => [
    'mode' => 'blacklist',
    'rules' => [
      [
        'field'    => 'NAME',
        'operator' => 'regex',
        'pattern'  => '/(1\/2|1\/4|3\/4|остаток|обрез)/ui', // Отсекает 1/2, 1/4, 3/4 и остатки
      ]
    ]
  ],

  // 4. МАППИНГ СВОЙСТВ В NICOLE CORE (C настраиваемым RegexExtractor)
  'property_map' => [
    'brand'         => ['source' => 'BRAND_REF',   'type' => 'hl',   'prefix' => 'opt_brand_'],
    'color'         => ['source' => 'COLOR',       'type' => 'hl',   'prefix' => 'opt_color_'],
    'texture'       => ['source' => 'TEXTURA',     'type' => 'enum', 'prefix' => 'opt_texture_'],
    'collection'    => ['source' => 'COLLECTION',  'type' => 'enum', 'prefix' => 'opt_collection_'],
    'effect_akril'  => ['source' => 'EFFECT_AKRIL', 'type' => 'enum', 'prefix' => 'opt_prefix_'],

    'is_bend'       => [
      'source' => 'BEND_AKRIL',
      'type' => 'enum',
      'transformers' => [
        [
          'class' => 'EnumToBoolean',
          'options' => ['truthy' => ['yes_bend', 'yes', 'y', '1']]
        ]
      ]
    ],

    // Извлечение Длины (группа 1)
    'length' => [
      'source' => 'FORMAT_SLEB',
      'transformers' => [
        [
          'class' => 'RegexExtractor',
          'options' => [
            'pattern' => '/(\d+)[\*xх×](\d+)[\*xх×](\d+)/ui',
            'group'   => 1
          ]
        ]
      ]
    ],

    // Извлечение Ширины (группа 2)
    'width' => [
      'source' => 'FORMAT_SLEB',
      'transformers' => [
        [
          'class' => 'RegexExtractor',
          'options' => [
            'pattern' => '/(\d+)[\*xх×](\d+)[\*xх×](\d+)/ui',
            'group'   => 2
          ]
        ]
      ]
    ],

    // Извлечение Толщины (группа 3)
    'height' => [
      'source' => 'depth',
      'transformers' => ['CastToInteger']
    ],
  ],

  // ------------------------------------------------------------------
  // 5. ПОДДЕРЖИВАЕМЫЕ ВАЛЮТЫ И ТИПЫ ЦЕН КЛИЕНТА (Курсы берутся из БД)
  // ------------------------------------------------------------------
  'currencies' => [
    'RUB' => ['symbol' => '₽', 'name' => ['ru' => 'Российский рубль'], 'is_default' => true],
    'USD' => ['symbol' => '$', 'name' => ['ru' => 'Доллар США'],        'is_default' => false],
    'EUR' => ['symbol' => '€', 'name' => ['ru' => 'Евро'],              'is_default' => false],
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