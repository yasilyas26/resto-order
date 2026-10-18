<?php

namespace App\Enums;

enum RefundStatus: string
{
    case None = 'none';
    case Needed = 'needed';
    case Refunded = 'refunded';
}
