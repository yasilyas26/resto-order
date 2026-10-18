<?php

namespace App\Services\Payments;

enum NotificationResult: string
{
    case Processed = 'processed';
    case InvalidSignature = 'invalid_signature';
    case UnknownPayment = 'unknown_payment';
    case AmountMismatch = 'amount_mismatch';
}
