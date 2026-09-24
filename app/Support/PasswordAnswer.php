<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Forgiving password comparison: letter case, diacritics and extra spaces
 * don't matter ("  Žába " matches "zaba").
 */
class PasswordAnswer
{
    public static function normalize(string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_strtolower(Str::ascii($collapsed));
    }

    /** @param  list<string>  $accepted */
    public static function matches(string $given, array $accepted): bool
    {
        $given = self::normalize($given);

        if ($given === '') {
            return false;
        }

        foreach ($accepted as $answer) {
            if (self::normalize($answer) === $given) {
                return true;
            }
        }

        return false;
    }

    /**
     * The dozor's textarea (one accepted answer per line) as a clean list.
     *
     * @return list<string>
     */
    public static function parseLines(?string $lines): array
    {
        return collect(preg_split('/\R/u', (string) $lines))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => $line !== '')
            ->unique(fn ($line) => self::normalize($line))
            ->values()
            ->all();
    }
}
