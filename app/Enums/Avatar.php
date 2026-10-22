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
    case Wolf = 'wolf';
    case Boar = 'boar';
    case Badger = 'badger';
    case Mouse = 'mouse';
    case Bat = 'bat';
    case Duck = 'duck';
    case Turtle = 'turtle';
    case Snail = 'snail';
    case Ladybug = 'ladybug';
    case Bee = 'bee';
    case Butterfly = 'butterfly';
    case Lion = 'lion';

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
            self::Wolf => 'Vlk',
            self::Boar => 'Divočák',
            self::Badger => 'Jezevec',
            self::Mouse => 'Myška',
            self::Bat => 'Netopýr',
            self::Duck => 'Kachna',
            self::Turtle => 'Želva',
            self::Snail => 'Šnek',
            self::Ladybug => 'Beruška',
            self::Bee => 'Včelka',
            self::Butterfly => 'Motýl',
            self::Lion => 'Lev',
        };
    }
}
