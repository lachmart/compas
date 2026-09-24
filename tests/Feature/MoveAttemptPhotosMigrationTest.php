<?php

use App\Models\LocationAttempt;
use Illuminate\Support\Facades\Storage;

test('old public photos move to the private disk without metadata', function () {
    Storage::fake('public');
    Storage::fake('local');

    $image = imagecreatetruecolor(30, 20);
    ob_start();
    imagejpeg($image);
    Storage::disk('public')->put('attempt-photos/old.jpg', ob_get_clean());
    Storage::disk('public')->put('attempt-photos/broken.jpg', 'not an image');

    $moved = LocationAttempt::factory()->create(['submitted_photo_path' => 'attempt-photos/old.jpg']);
    $broken = LocationAttempt::factory()->create(['submitted_photo_path' => 'attempt-photos/broken.jpg']);
    $missing = LocationAttempt::factory()->create(['submitted_photo_path' => 'attempt-photos/gone.jpg']);

    (require database_path('migrations/2026_09_24_000002_move_attempt_photos_to_private_disk.php'))->up();

    $newPath = $moved->fresh()->submitted_photo_path;
    expect($newPath)->not->toBe('attempt-photos/old.jpg')->toStartWith('attempt-photos/');
    Storage::disk('local')->assertExists($newPath);
    Storage::disk('public')->assertMissing('attempt-photos/old.jpg');

    expect($broken->fresh()->submitted_photo_path)->toBeNull();
    Storage::disk('public')->assertMissing('attempt-photos/broken.jpg');

    expect($missing->fresh()->submitted_photo_path)->toBe('attempt-photos/gone.jpg');
});
