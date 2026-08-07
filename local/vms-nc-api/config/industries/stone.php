<?php

declare(strict_types=1);

return [
  'industry_code' => 'stone',
  'industry_name' => ['ru' => 'Индустрия камня'],

  // 1. Семейства товаров
  'families' => [
    [
      'external_code' => 'fam_stone',
      'code' => 'stone',
      'name' => ['ru' => 'Искусственный камень'],
      'meta_schema' => [
        ['key' => 'step', 'type' => 'number', 'label' => ['ru' => 'Шаг размера'], 'width' => 1],
        ['key' => 'minPart', 'type' => 'number', 'label' => ['ru' => 'Минимальная часть'], 'width' => 1],
        ['key' => 'maxStack', 'type' => 'number', 'label' => ['ru' => 'Макс. стопка'], 'width' => 1],
        ['key' => 'axisX', 'type' => 'boolean', 'label' => ['ru' => 'По оси X'], 'width' => 1],
        ['key' => 'is_separate', 'type' => 'boolean', 'label' => ['ru' => 'Кроить раздельно'], 'width' => 2],
        ['key' => 'corner_add_length', 'type' => 'number', 'label' => ['ru' => 'Добавка на внутр. угол (Длина мм)'], 'width' => 1],
        ['key' => 'corner_add_width', 'type' => 'number', 'label' => ['ru' => 'Добавка на внутр. угол (Ширина мм)'], 'width' => 1],
        ['key' => 'allow_rounding', 'type' => 'boolean', 'label' => ['ru' => 'Допускает скругления'], 'width' => 1],
      ]
    ],
    ['external_code' => 'fam_sink', 'code' => 'sink', 'name' => ['ru' => 'Кухонные мойки']],
    ['external_code' => 'fam_faucet', 'code' => 'faucet', 'name' => ['ru' => 'Смесители и дозаторы']],
    ['external_code' => 'fam_bowl', 'code' => 'bowl', 'name' => ['ru' => 'Раковины для ванной']],
    ['external_code' => 'fam_accessory', 'code' => 'accessory', 'name' => ['ru' => 'Комплектующие и бортики']],
    ['external_code' => 'fam_natural_stone', 'code' => 'natural-stone', 'name' => ['ru' => 'Натуральный камень']],
  ],

  // 2. Типы товаров
  'types' => [
    [
      'external_code' => 'type_acrylic_stone',
      'family_external_code' => 'fam_stone',
      'code' => 'acrylic_stone',
      'name' => ['ru' => 'Акриловый камень'],
      'meta' => [
        'step' => 0.5, 'maxStack' => 1, 'axisX' => true, 'minPart' => 12,
        'is_separate' => false, 'corner_add_length' => 920, 'corner_add_width' => 760, 'allow_rounding' => true
      ],
      'attached_attributes' => [
        ['code' => 'supplier_article', 'is_variant_only' => true],
        ['code' => 'effect_akril', 'is_variant_only' => false],
        ['code' => 'is_bend', 'is_variant_only' => false],
        ['code' => 'texture', 'is_variant_only' => false],
        ['code' => 'inclusions_akril', 'is_variant_only' => false],
        ['code' => 'brand', 'is_variant_only' => false],
        ['code' => 'color', 'is_variant_only' => true],
        ['code' => 'collection', 'is_variant_only' => false],
        ['code' => 'length', 'is_variant_only' => false],
        ['code' => 'width', 'is_variant_only' => false],
        ['code' => 'height', 'is_variant_only' => false],
        ['code' => 'cutting_groups', 'is_variant_only' => false],
      ],
      'pricing_mode' => 'complex_dictionary',
      'pricing_attr_code' => null,
      'pricing_field' => 'purchase_cost'
    ],
    [
      'external_code' => 'type_quartz_stone',
      'family_external_code' => 'fam_stone',
      'code' => 'quartz_stone',
      'name' => ['ru' => 'Кварцевый агломерат'],
      'meta' => [
        'step' => 1, 'maxStack' => 1, 'axisX' => false, 'minPart' => 20,
        'is_separate' => true, 'corner_add_length' => 750, 'corner_add_width' => 700, 'allow_rounding' => false
      ],
      'attached_attributes' => [
        ['code' => 'supplier_article', 'is_variant_only' => true],
        ['code' => 'texture', 'is_variant_only' => false],
        ['code' => 'polishing_quartz', 'is_variant_only' => false],
        ['code' => 'brand', 'is_variant_only' => false],
        ['code' => 'color', 'is_variant_only' => true],
        ['code' => 'collection', 'is_variant_only' => false],
        ['code' => 'length', 'is_variant_only' => false],
        ['code' => 'width', 'is_variant_only' => false],
        ['code' => 'height', 'is_variant_only' => false],
        ['code' => 'cutting_groups', 'is_variant_only' => false],
      ],
      'pricing_mode' => 'complex_dictionary',
      'pricing_attr_code' => null,
      'pricing_field' => 'purchase_cost'
    ],
    [
      'external_code' => 'type_kitchen_sink',
      'family_external_code' => 'fam_sink',
      'code' => 'kitchen_sink',
      'name' => ['ru' => 'Кухонная мойка'],
      'meta' => [],
      'attached_attributes' => [
        ['code' => 'supplier_article', 'is_variant_only' => true],
        ['code' => 'brand', 'is_variant_only' => false],
        ['code' => 'set_sink', 'is_variant_only' => false],
        ['code' => 'material', 'is_variant_only' => false],
        ['code' => 'steel_thickness_sink', 'is_variant_only' => false],
        ['code' => 'min_cab_width', 'is_variant_only' => false],
        ['code' => 'color', 'is_variant_only' => true],
        ['code' => 'size_inner_sink', 'is_variant_only' => false],
      ],
      'pricing_mode' => 'manual',
      'pricing_attr_code' => null,
      'pricing_field' => null
    ],
    [
      'external_code' => 'type_faucet',
      'family_external_code' => 'fam_faucet',
      'code' => 'faucet',
      'name' => ['ru' => 'Смеситель'],
      'meta' => [],
      'attached_attributes' => [
        ['code' => 'supplier_article', 'is_variant_only' => true],
        ['code' => 'brand', 'is_variant_only' => false],
        ['code' => 'material', 'is_variant_only' => false],
        ['code' => 'features_faucet', 'is_variant_only' => false],
        ['code' => 'type_faucet', 'is_variant_only' => false],
        ['code' => 'color', 'is_variant_only' => true],
      ],
      'pricing_mode' => 'manual',
      'pricing_attr_code' => null,
      'pricing_field' => null
    ],
    [
      'external_code' => 'type_dispenser',
      'family_external_code' => 'fam_faucet',
      'code' => 'dispenser',
      'name' => ['ru' => 'Дозатор'],
      'meta' => [],
      'attached_attributes' => [
        ['code' => 'supplier_article', 'is_variant_only' => true],
        ['code' => 'brand', 'is_variant_only' => false],
        ['code' => 'type_faucet', 'is_variant_only' => false],
        ['code' => 'color', 'is_variant_only' => true],
      ],
      'pricing_mode' => 'manual',
      'pricing_attr_code' => null,
      'pricing_field' => null
    ],
    [
      'external_code' => 'type_bathroom_sink',
      'family_external_code' => 'fam_bowl',
      'code' => 'bathroom_sink',
      'name' => ['ru' => 'Раковина для ванной'],
      'meta' => [],
      'attached_attributes' => [
        ['code' => 'supplier_article', 'is_variant_only' => true],
        ['code' => 'brand', 'is_variant_only' => false],
        ['code' => 'set_sink', 'is_variant_only' => false],
        ['code' => 'material', 'is_variant_only' => false],
        ['code' => 'color', 'is_variant_only' => true],
        ['code' => 'size_inner_sink', 'is_variant_only' => false],
      ],
      'pricing_mode' => 'manual',
      'pricing_attr_code' => null,
      'pricing_field' => null
    ],
    [
      'external_code' => 'type_edge',
      'family_external_code' => 'fam_accessory',
      'code' => 'edge',
      'name' => ['ru' => 'Кромка (п.м.)'],
      'meta' => [],
      'attached_attributes' => [],
      'pricing_mode' => 'manual',
      'pricing_attr_code' => null,
      'pricing_field' => null
    ],
    [
      'external_code' => 'type_skirting',
      'family_external_code' => 'fam_accessory',
      'code' => 'skirting',
      'name' => ['ru' => 'Бортик (п.м.)'],
      'meta' => [],
      'attached_attributes' => [],
      'pricing_mode' => 'manual',
      'pricing_attr_code' => null,
      'pricing_field' => null
    ],
  ],

  // 3. Ценовые группы (Price Groups)
  'price_groups' => [
    [
      'external_code' => 'pg_v0',
      'product_family_external_code' => 'fam_stone',
      'slug' => 'v0',
      'name' => ['ru' => 'V0'],
      'description' => ['ru' => 'Базовая категория'],
      'meta' => [
        'purchase_cost' => 295,
        'purchase_currency' => 'USD',
        'markup_retail' => 30
      ]
    ]
  ],

  // 4. Умные справочники (Complex Dictionaries)
  'complex_dictionaries' => [
    [
      'external_code' => 'dict_cutting_groups',
      'code' => 'cutting_groups',
      'name' => ['ru' => 'Группы раскроя'],
      'meta_schema' => [
        ['key' => 'rotate', 'type' => 'boolean', 'label' => ['ru' => 'Повтор рисунка']],
        ['key' => 'cut', 'type' => 'boolean', 'label' => ['ru' => 'Раздельный раскрой']]
      ],
      'records' => [
        [
          'external_code' => 'rec_cutting_groups_1',
          'slug' => '1',
          'name' => ['ru' => 'Раскрой: Раздельный | Шов: Разрешен | Поворот: Разрешен'],
          'meta' => ['rotate' => true, 'cut' => true]
        ],
        [
          'external_code' => 'rec_cutting_groups_2',
          'slug' => '2',
          'name' => ['ru' => 'Раскрой: Совместный | Шов: Разрешен | Поворот: Разрешен'],
          'meta' => ['rotate' => true, 'cut' => false]
        ],
        [
          'external_code' => 'rec_cutting_groups_3',
          'slug' => '3',
          'name' => ['ru' => 'Раскрой: Раздельный | Шов: Разрешен | Поворот: Запрещен'],
          'meta' => ['rotate' => false, 'cut' => true]
        ],
        [
          'external_code' => 'rec_cutting_groups_4',
          'slug' => '4',
          'name' => ['ru' => 'Раскрой: Совместный | Шов: Запрещен | Поворот: Запрещен'],
          'meta' => ['rotate' => false, 'cut' => false]
        ]
      ]
    ],
    [
      'external_code' => 'dict_thicknesses',
      'code' => 'thicknesses',
      'name' => ['ru' => 'Коэффициенты толщин'],
      'meta_schema' => [
        ['key' => 'material_code', 'type' => 'text', 'label' => ['ru' => 'Системный код материала']],
        ['key' => 'thickness', 'type' => 'number', 'label' => ['ru' => 'Толщина (мм)']],
        ['key' => 'coefficient', 'type' => 'number', 'label' => ['ru' => 'Коэффициент наценки']]
      ],
      'records' => [
        [
          'external_code' => 'rec_thick_acr_6',
          'slug' => 'acr_6',
          'name' => ['ru' => 'Акрил 6мм'],
          'meta' => ['material_code' => 'acrylic_stone', 'thickness' => 6, 'coefficient' => 0.8]
        ],
        [
          'external_code' => 'rec_thick_acr_12',
          'slug' => 'acr_12',
          'name' => ['ru' => 'Акрил 12мм'],
          'meta' => ['material_code' => 'acrylic_stone', 'thickness' => 12, 'coefficient' => 1]
        ],
        [
          'external_code' => 'rec_thick_acr_20',
          'slug' => 'acr_20',
          'name' => ['ru' => 'Акрил 20мм'],
          'meta' => ['material_code' => 'acrylic_stone', 'thickness' => 20, 'coefficient' => 1.5]
        ],
        [
          'external_code' => 'rec_thick_qtz_15',
          'slug' => 'qtz_15',
          'name' => ['ru' => 'Кварц 15мм'],
          'meta' => ['material_code' => 'quartz_stone', 'thickness' => 15, 'coefficient' => 0.9]
        ],
        [
          'external_code' => 'rec_thick_qtz_20',
          'slug' => 'qtz_20',
          'name' => ['ru' => 'Кварц 20мм'],
          'meta' => ['material_code' => 'quartz_stone', 'thickness' => 20, 'coefficient' => 1]
        ],
        [
          'external_code' => 'rec_thick_qtz_30',
          'slug' => 'qtz_30',
          'name' => ['ru' => 'Кварц 30мм'],
          'meta' => ['material_code' => 'quartz_stone', 'thickness' => 30, 'coefficient' => 1.4]
        ]
      ]
    ]
  ]
];