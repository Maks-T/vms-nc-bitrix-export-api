<?php

declare(strict_types=1);

namespace VmsNcApi\Helpers;

final class LocalizationHelper
{
  /**
   * Приводит строку или массив переводов строго к языковому контексту клиента
   *
   * @param string|array $value Строка ("Рубль") или массив (['ru' => "Рубль", 'en' => "Ruble"])
   * @param array $supportedLocales Список языков клиента (например ['ru'] или ['ru', 'en', 'de'])
   * @param string $defaultLocale Язык по умолчанию
   * @return array
   */
  public static function format($value, array $supportedLocales = ['ru'], string $defaultLocale = 'ru'): array
  {
    $result = [];

    if (empty($supportedLocales)) {
      $supportedLocales = [$defaultLocale];
    }

    foreach ($supportedLocales as $locale) {
      if (is_array($value)) {
        // Если передан массив - берем перевод для языка, либо дефолтный, либо первое попавшееся значение
        $firstVal = reset($value);
        $result[$locale] = (string)($value[$locale] ?? ($value[$defaultLocale] ?? ($firstVal !== false ? $firstVal : '')));
      } else {
        // Если передана обычная строка - заполняем ею значение
        $result[$locale] = (string)$value;
      }
    }

    return $result;
  }
}