<?php

use App\Enums\AttemptStatus;
use App\Models\LocationAttempt;

test('location attempt casts status to enum', function () {
    $attempt = LocationAttempt::factory()->create(['status' => 'arrived']);

    expect($attempt->status)->toBe(AttemptStatus::Arrived);
});
