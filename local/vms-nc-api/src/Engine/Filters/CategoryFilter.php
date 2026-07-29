<?php

declare(strict_types=1);

namespace VmsNcApi\Engine\Filters;

final class CategoryFilter
{
  public static function isSectionAllowed(int $sectionId, array $categoryConfig): bool
  {
    $mode = $categoryConfig['mode'] ?? 'whitelist';
    $includeIds = array_map('intval', $categoryConfig['include_section_ids'] ?? []);
    $excludeIds = array_map('intval', $categoryConfig['exclude_section_ids'] ?? []);

    if (in_array($sectionId, $excludeIds, true)) {
      return false;
    }

    if ($mode === 'whitelist' && !empty($includeIds)) {
      return in_array($sectionId, $includeIds, true);
    }

    return true;
  }
}