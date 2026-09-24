<?php

use App\Support\PasswordAnswer;

test('case, diacritics and spaces do not matter', function (string $given) {
    expect(PasswordAnswer::matches($given, ['Žába']))->toBeTrue();
})->with(['žába', 'ZABA', '  zaba ', 'Žába']);

test('inner spaces are collapsed', function () {
    expect(PasswordAnswer::matches('velky   dub', ['Velký dub']))->toBeTrue();
});

test('any of several accepted answers matches', function () {
    expect(PasswordAnswer::matches('liska', ['Lišák', 'liška']))->toBeTrue();
});

test('a wrong or empty answer does not match', function (string $given) {
    expect(PasswordAnswer::matches($given, ['žába']))->toBeFalse();
})->with(['žábař', 'zab', '', '   ']);

test('the dozor textarea becomes a clean list of answers', function () {
    expect(PasswordAnswer::parseLines(" Žába \n\nzaba\r\nRosnička\n"))->toBe(['Žába', 'Rosnička']);
});
