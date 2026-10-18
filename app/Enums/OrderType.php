<?php

namespace App\Enums;

enum OrderType: string
{
    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine In',
            self::Takeaway => 'Bawa Pulang',
        };
    }
}
