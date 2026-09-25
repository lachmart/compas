<?php

namespace App\Enums;

enum TaskType: string
{
    case ArrivalOnly = 'arrival_only';
    case PickImage = 'pick_image';
    case PhotoOfSomething = 'photo_of_something';
    case SelfieDoingSomething = 'selfie_doing_something';
    case Password = 'password';

    /** The player takes a photo (after a reference picture). */
    public function isPhoto(): bool
    {
        return $this === self::PhotoOfSomething || $this === self::SelfieDoingSomething;
    }
}
