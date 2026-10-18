<?php

namespace App\Enums;

/** Status dapur. Hanya diubah karyawan, dan hanya untuk order yang sudah dibayar. */
enum KitchenStatus: string
{
    case Queued = 'queued';
    case Cooking = 'cooking';
    case Done = 'done';
}
