<?php

use App\Models\Location;

test('images() returns the pick_image payload images', function () {
    $location = Location::factory()->create([
        'task_type' => 'pick_image',
        'task_payload' => ['images' => ['a.jpg', 'b.jpg', 'c.jpg'], 'correct_index' => 1],
    ]);

    expect($location->images())->toBe(['a.jpg', 'b.jpg', 'c.jpg']);
});

test('correctImageIndex() returns the pick_image correct index', function () {
    $location = Location::factory()->create([
        'task_type' => 'pick_image',
        'task_payload' => ['images' => ['a.jpg', 'b.jpg', 'c.jpg'], 'correct_index' => 2],
    ]);

    expect($location->correctImageIndex())->toBe(2);
});

test('referenceImage() returns the photo task reference image', function () {
    $location = Location::factory()->create([
        'task_type' => 'photo_of_something',
        'task_payload' => ['reference_image' => 'ref.jpg'],
    ]);

    expect($location->referenceImage())->toBe('ref.jpg');
});

test('accessors return safe defaults for arrival_only', function () {
    $location = Location::factory()->create([
        'task_type' => 'arrival_only',
        'task_payload' => null,
    ]);

    expect($location->images())->toBe([])
        ->and($location->correctImageIndex())->toBeNull()
        ->and($location->referenceImage())->toBeNull();
});
