<?php

use App\Models\Game;
use App\Support\SlugGenerator;

test('generates a slug from the name', function () {
    expect(SlugGenerator::unique('Lesní dobrodružství'))->toBe('lesni-dobrodruzstvi');
});

test('appends a numeric suffix when the slug is taken', function () {
    Game::factory()->create(['slug' => 'lesni-dobrodruzstvi']);

    expect(SlugGenerator::unique('Lesní dobrodružství'))->toBe('lesni-dobrodruzstvi-2');
});

test('increments the suffix past the first collision', function () {
    Game::factory()->create(['slug' => 'lesni-dobrodruzstvi']);
    Game::factory()->create(['slug' => 'lesni-dobrodruzstvi-2']);

    expect(SlugGenerator::unique('Lesní dobrodružství'))->toBe('lesni-dobrodruzstvi-3');
});
