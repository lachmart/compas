<?php

use App\Enums\Avatar;

test('the PHP animals match the JS list key for key', function () {
    $js = file_get_contents(resource_path('js/lib/avatars.js'));
    preg_match_all("/key: '([a-z]+)'/", $js, $matches);

    expect($matches[1])->toBe(array_map(fn (Avatar $avatar) => $avatar->value, Avatar::cases()));
});

test('every animal fits the avatar column', function () {
    foreach (Avatar::cases() as $avatar) {
        expect(strlen($avatar->value))->toBeLessThanOrEqual(20);
    }
});
