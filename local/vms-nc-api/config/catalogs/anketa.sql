-- ============================================================================
-- АНКЕТНЫЙ ПАКЕТ SQL-ЗАПРОСОВ ДЛЯ ЭКСПРЕСС-АУДИТА КАТАЛОГА 1С-БИТРИКС
-- ============================================================================
-- Инструкция: Выполняйте запросы по очереди в админке Битрикса:
-- Настройки ➔ Производительность ➔ SQL-запрос
-- Результаты используются для заполнения файла config/catalogs/{client_code}.php
-- ============================================================================


-- ----------------------------------------------------------------------------
-- ШАГ 1. Поиск инфоблоков каталогов и их торговых предложений (SKU)
-- Назначение: Заполнение секции 'catalogs' => [ ['catalog' => ID, 'offers' => ID] ]
-- ----------------------------------------------------------------------------
SELECT
    c.IBLOCK_ID AS catalog_iblock_id,
    i1.NAME AS catalog_name,
    c.OFFERS_IBLOCK_ID AS offers_iblock_id,
    i2.NAME AS offers_name,
    c.SKU_PROPERTY_ID
FROM b_catalog_iblock c
         LEFT JOIN b_iblock i1 ON i1.ID = c.IBLOCK_ID
         LEFT JOIN b_iblock i2 ON i2.ID = c.OFFERS_IBLOCK_ID
ORDER BY c.IBLOCK_ID;


-- ----------------------------------------------------------------------------
-- ШАГ 2. Выгрузка всех активных разделов и их глубины (Дерево категорий)
-- Назначение: Заполнение секций 'include_section_ids' и 'product_type_rules'
-- ----------------------------------------------------------------------------
SELECT
    s.IBLOCK_ID,
    i.NAME AS IBLOCK_NAME,
    s.ID AS SECTION_ID,
    s.IBLOCK_SECTION_ID AS PARENT_SECTION_ID,
    s.NAME AS SECTION_NAME,
    s.CODE AS SECTION_CODE,
    s.DEPTH_LEVEL,
    COUNT(e.ID) AS ELEMENT_COUNT
FROM b_iblock_section s
         INNER JOIN b_iblock i ON i.ID = s.IBLOCK_ID
         LEFT JOIN b_iblock_element e ON e.IBLOCK_SECTION_ID = s.ID AND e.ACTIVE = 'Y'
WHERE s.ACTIVE = 'Y'
GROUP BY s.ID, s.IBLOCK_ID, i.NAME, s.IBLOCK_SECTION_ID, s.NAME, s.CODE, s.DEPTH_LEVEL
ORDER BY s.IBLOCK_ID, s.DEPTH_LEVEL, s.SORT;


-- ----------------------------------------------------------------------------
-- ШАГ 3. Полный аудит свойств с проверкой их заполнености
-- Назначение: Заполнение секций 'property_map' и 'product_filters' (показывает FILLED_COUNT)
-- ----------------------------------------------------------------------------
SELECT
    p.IBLOCK_ID,
    i.NAME AS IBLOCK_NAME,
    p.ID AS PROPERTY_ID,
    p.NAME AS PROPERTY_NAME,
    p.CODE AS PROPERTY_CODE,
    p.PROPERTY_TYPE,
    p.USER_TYPE,
    COUNT(ep.IBLOCK_ELEMENT_ID) AS FILLED_COUNT
FROM b_iblock_property p
         INNER JOIN b_iblock i ON i.ID = p.IBLOCK_ID
         LEFT JOIN b_iblock_element_property ep ON ep.IBLOCK_PROPERTY_ID = p.ID
WHERE p.ACTIVE = 'Y'
GROUP BY p.ID, p.IBLOCK_ID, i.NAME, p.NAME, p.CODE, p.PROPERTY_TYPE, p.USER_TYPE
ORDER BY p.IBLOCK_ID, FILLED_COUNT DESC;


-- ----------------------------------------------------------------------------
-- ШАГ 4. Список всех Highload-блоков (Справочники Брендов, Цветов, Коллекций)
-- Назначение: Определение имен таблиц для 'type' => 'hl' в property_map
-- ----------------------------------------------------------------------------
SELECT ID, NAME, TABLE_NAME
FROM b_hlblock_entity
ORDER BY ID;


-- ----------------------------------------------------------------------------
-- ШАГ 5. Проверка валют и их внутренних курсов
-- Назначение: Заполнение секций 'currencies', 'currency_map' и 'currency_converter'
-- ----------------------------------------------------------------------------
SELECT CURRENCY, AMOUNT_CNT, AMOUNT
FROM b_catalog_currency;


-- ----------------------------------------------------------------------------
-- ШАГ 6. Проверка типов цен (Розница, Закупка, Опт)
-- Назначение: Определение ID базовых цен для секции 'pricing' (retail_group_id, cost_group_id)
-- ----------------------------------------------------------------------------
SELECT ID, NAME, BASE, SORT
FROM b_catalog_group
ORDER BY SORT;


-- ----------------------------------------------------------------------------
-- ШАГ 7. Образец названий и кодов элементов (для составления регулярных выражений)
-- Назначение: Анализ паттернов названий ТП для настройки секции 'offer_filters'
-- ----------------------------------------------------------------------------
SELECT ID, IBLOCK_ID, NAME, CODE
FROM b_iblock_element
WHERE ACTIVE = 'Y'
ORDER BY ID DESC
    LIMIT 50;