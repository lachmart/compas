<?php

namespace App\Enums;

enum TaskType: string
{
    case ArrivalOnly = 'arrival_only';
    case PickImage = 'pick_image';
    case PhotoOfSomething = 'photo_of_something';
    case SelfieDoingSomething = 'selfie_doing_something';
}
