<?php

return [
  'client_code' => 'stoleshka_ru',
  'industry'    => 'stone',

  // 1. ИД ИНФОБЛОКОВ
  'iblocks' => [
    'catalog' => 2, // Инфоблок "Камень"
    'offers'  => 3, // Торговые предложения
  ],

  // ------------------------------------------------------------------
  // 2. ФИЛЬТРАЦИЯ КАТЕГОРИЙ (РАЗДЕЛОВ БИТРИКСА)
  // ------------------------------------------------------------------
  'category_filters' => [
    'mode' => 'whitelist', // whitelist - только указанные, blacklist - все кроме указанных
    'include_section_ids' => [16, 17, 18, 19, 20, 23, 24, 25, 26, 37, 38, 39, 43, 44, 45, 46, 47, 48, 49], // Только нужные ветки
    'exclude_section_ids' => [14, 99], // Явное исключение черновиков/архивов

    // Маппинг синонимов префиксов для external_code категорий
    'external_code_prefix' => 'cat_'
  ],

  // ------------------------------------------------------------------
  // 3. ФИЛЬТРАЦИЯ ТОРГОВЫХ ПРЕДЛОЖЕНИЙ (SKU)
  // ------------------------------------------------------------------
  'offer_filters' => [
    'mode' => 'whitelist',
    'rules' => [
      [
        'field'    => 'NAME',
        'operator' => 'regex',
        'pattern'  => '/целый сл[еэ]б/ui', // Отсекаем 1/2, 1/4
      ]
    ]
  ],

  // ------------------------------------------------------------------
  // 4. МАППИНГ СВОЙСТВ В EAV СТАНДАРТ NICOLE CORE
  // ------------------------------------------------------------------
  'property_map' => [
    'brand'         => ['source' => 'BRAND_REF',  'type' => 'hl',   'prefix' => 'opt_brand_'],
    'color'         => ['source' => 'COLOR',      'type' => 'hl',   'prefix' => 'opt_color_'],
    'texture'       => ['source' => 'TEXTURA',    'type' => 'enum', 'prefix' => 'opt_texture_'],
    'collection'    => ['source' => 'COLLECTION', 'type' => 'enum', 'prefix' => 'opt_collection_'],
    'effect_akril'  => ['source' => 'EFFECT_AKRIL','type' => 'enum', 'prefix' => 'opt_effect_akril_'],
    'is_bend'       => ['source' => 'BEND_AKRIL', 'type' => 'enum', 'transformer' => 'EnumToBoolean'],

    // Извлечение физических размеров из полей Битрикса
    'length'        => ['source' => 'FORMAT_SLEB', 'transformers' => ['RegexLength']],
    'width'         => ['source' => 'FORMAT_SLEB', 'transformers' => ['RegexWidth']],
    'height'        => ['source' => 'depth',       'transformers' => ['CastToInteger']],
  ],

  // ------------------------------------------------------------------
  // 5. ВАЛЮТЫ И ТИПЫ ЦЕН КЛИЕНТА ДЛЯ JSON
  // ------------------------------------------------------------------
  'currencies' => [
    'BYN' => ['symbol' => 'Br', 'rate' => 1.0, 'is_default' => true],
    'RUB' => ['symbol' => '₽',  'rate' => 0.0339, 'is_default' => false],
    'USD' => ['symbol' => '$',  'rate' => 3.2, 'is_default' => false],
  ],

  'price_types' => [
    [
      'slug' => 'retail',
      'currency_code' => 'BYN',
      'is_default' => true,
      'name' => ['ru' => 'Розничная цена'],
    ]
  ]
];