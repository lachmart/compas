<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case Pending = 'pending';
    case Arrived = 'arrived';
    case Completed = 'completed';
}
