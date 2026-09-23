<?php

namespace App\Enums;

enum Avatar: string
{
    case Fox = 'fox';
    case Hedgehog = 'hedgehog';
    case Owl = 'owl';
    case Rabbit = 'rabbit';
    case Bear = 'bear';
    case Frog = 'frog';
    case Squirrel = 'squirrel';
    case Deer = 'deer';

    public function label(): string
    {
        return match ($this) {
            self::Fox => 'Liška',
            self::Hedgehog => 'Ježek',
            self::Owl => 'Sova',
            self::Rabbit => 'Zajíc',
            self::Bear => 'Medvěd',
            self::Frog => 'Žába',
            self::Squirrel => 'Veverka',
            self::Deer => 'Jelen',
        };
    }
}
