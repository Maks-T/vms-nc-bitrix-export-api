<?php

declare(strict_types=1);

namespace VmsNcApi\Constants;

final class AttributeSettings
{
  /**
   * Публичный атрибут с фильтром Чекбоксы (для Брендов, Текстур, Коллекций)
   */
  public static function checkbox(bool $isCollapsed = false): array
  {
    return [
      'channels' => [
        'widget'  => ['is_public' => true, 'is_settings_public' => true, 'is_filterable' => true, 'is_collapsed' => $isCollapsed, 'filter_type' => 'checkbox'],
        'catalog' => ['is_public' => true, 'is_settings_public' => true, 'is_filterable' => true, 'is_collapsed' => $isCollapsed, 'filter_type' => 'checkbox'],
      ],
    ];
  }

  /**
   * Публичный атрибут с фильтром Цветовые кружки (для Цвета)
   */
  public static function color(bool $isCollapsed = false): array
  {
    return [
      'channels' => [
        'widget'  => ['is_public' => true, 'is_settings_public' => true, 'is_filterable' => true, 'is_collapsed' => $isCollapsed, 'filter_type' => 'color'],
        'catalog' => ['is_public' => true, 'is_settings_public' => true, 'is_filterable' => true, 'is_collapsed' => $isCollapsed, 'filter_type' => 'color'],
      ],
    ];
  }

  /**
   * Публичный атрибут с фильтром Слайдер диапазона (для Длины, Ширины, Толщины)
   */
  public static function range(bool $isCollapsed = false): array
  {
    return [
      'channels' => [
        'widget'  => ['is_public' => true, 'is_settings_public' => true, 'is_filterable' => true, 'is_collapsed' => $isCollapsed, 'filter_type' => 'range'],
        'catalog' => ['is_public' => true, 'is_settings_public' => true, 'is_filterable' => true, 'is_collapsed' => $isCollapsed, 'filter_type' => 'range'],
      ],
    ];
  }

  /**
   * Скрытый из UI-фильтров атрибут (для Артикула)
   */
  public static function hidden(): array
  {
    return [
      'channels' => [
        'widget'  => ['is_public' => true, 'is_settings_public' => true, 'is_filterable' => false],
        'catalog' => ['is_public' => true, 'is_settings_public' => true, 'is_filterable' => false],
      ],
    ];
  }
}