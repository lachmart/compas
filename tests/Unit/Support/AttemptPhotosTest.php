<?php

use App\Support\AttemptPhotos;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

// A width×height JPEG whose APP1 EXIF says Make "KompasTestPhone" and the
// given orientation — what a phone camera writes.
function jpegWithExif(int $width, int $height, int $orientation): string
{
    $image = imagecreatetruecolor($width, $height);
    // Top-left quarter red, so rotation can be checked by pixel colour.
    imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, intdiv($height, 2) - 1, imagecolorallocate($image, 255, 0, 0));
    ob_start();
    imagejpeg($image, null, 90);
    $jpeg = ob_get_clean();

    $make = "KompasTestPhone\0";
    $tiff = 'II'.pack('v', 42).pack('V', 8)
        .pack('v', 2)
        .pack('vvVV', 0x010F, 2, strlen($make), 38)
        .pack('vvVvv', 0x0112, 3, 1, $orientation, 0)
        .pack('V', 0)
        .$make;
    $app1 = "Exif\0\0".$tiff;

    $path = tempnam(sys_get_temp_dir(), 'exif').'.jpg';
    file_put_contents($path, substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2));

    return $path;
}

test('the fixture really carries the EXIF data', function () {
    $exif = exif_read_data(jpegWithExif(40, 20, 6));

    expect($exif['Make'])->toBe('KompasTestPhone')
        ->and($exif['Orientation'])->toBe(6);
});

test('sanitising drops all metadata', function () {
    $clean = AttemptPhotos::sanitize(jpegWithExif(40, 20, 1));

    expect($clean)->not->toContain('Exif')
        ->and($clean)->not->toContain('KompasTestPhone');
});

test('the EXIF orientation is applied to the pixels', function () {
    // Orientation 6 = the camera was turned; the 40×20 sensor image is shown
    // rotated 90° clockwise as 20×40, the red corner moving to the top right.
    $image = imagecreatefromstring(AttemptPhotos::sanitize(jpegWithExif(40, 20, 6)));

    expect([imagesx($image), imagesy($image)])->toBe([20, 40]);

    $red = fn ($x, $y) => (imagecolorat($image, $x, $y) >> 16) & 0xFF;
    expect($red(15, 5))->toBeGreaterThan(200)
        ->and($red(5, 5))->toBeLessThan(60);
});

test('big photos are shrunk to the max edge', function () {
    $image = imagecreatefromstring(AttemptPhotos::sanitize(jpegWithExif(3200, 1600, 1)));

    expect([imagesx($image), imagesy($image)])->toBe([AttemptPhotos::MAX_EDGE, AttemptPhotos::MAX_EDGE / 2]);
});

test('a file that is not an image is rejected', function () {
    $path = tempnam(sys_get_temp_dir(), 'bad');
    file_put_contents($path, 'not an image');

    AttemptPhotos::sanitize($path);
})->throws(ValidationException::class);

test('photos are stored on the private disk', function () {
    Storage::fake('local');

    $path = AttemptPhotos::store(jpegWithExif(40, 20, 1));

    expect($path)->toStartWith('attempt-photos/')->toEndWith('.jpg');
    Storage::disk('local')->assertExists($path);
});
