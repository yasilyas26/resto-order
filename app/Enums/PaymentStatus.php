<?php

namespace App\Enums;

/** Status uang. Hanya boleh diubah oleh webhook Midtrans (atau sinkronisasi status resmi dari API Midtrans). */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Failed = 'failed';
}
