<?php

namespace App\Support;

use App\Models\Game;
use Illuminate\Support\Str;

class SlugGenerator
{
    public static function unique(string $name): string
    {
        $base = Str::slug($name) ?: 'hra';
        $slug = $base;
        $suffix = 2;

        while (Game::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
