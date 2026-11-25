<?php

namespace App\Support;

/**
 * Countries by ISO 3166 code ("EG"), named in the page language (lang/{en,ar}/countries.php): the countries of the
 * phone widget (config/dial_codes.php). The agent's country ("Become an agent" form, Admin → Agents).
 */
final class Countries
{
    /** @return list<string> every code */
    public static function codes(): array
    {
        return array_keys(trans('countries', [], 'en'));
    }

    /** "Egypt" / "مصر"; '' without a code, the code itself if unknown. */
    public static function name(?string $code, ?string $locale = null): string
    {
        if ($code === null || $code === '') {
            return '';
        }
        $names = trans('countries', [], $locale);

        return $names[strtoupper($code)] ?? $code;
    }

    /**
     * For a select: the site's main countries first (config cruise.phone_preferred_countries), then the rest
     * alphabetically ("ال" left out when sorting Arabic names, so الأردن sorts under أ... as people look for it).
     *
     * @return array{top: array<string, string>, rest: array<string, string>}
     */
    public static function options(?string $locale = null): array
    {
        $names = trans('countries', [], $locale);
        $top = [];
        foreach (config('cruise.phone_preferred_countries', []) as $code) {
            $code = strtoupper($code);
            if (isset($names[$code])) {
                $top[$code] = $names[$code];
            }
        }
        $rest = array_diff_key($names, $top);
        uasort($rest, fn ($a, $b) => strcmp(self::sortKey($a), self::sortKey($b)));

        return ['top' => $top, 'rest' => $rest];
    }

    private static function sortKey(string $name): string
    {
        return mb_strtolower(preg_replace('/^ال/u', '', $name));
    }
}
