<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\NotificationResult;
use App\Services\Payments\PaymentNotificationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransNotificationController extends Controller
{
    /**
     * Webhook dari server Midtrans. Satu-satunya jalur yang menetapkan order lunas.
     * Tanpa CSRF (bukan dari browser); keasliannya dijamin signature_key di handler.
     */
    public function __invoke(Request $request, PaymentNotificationHandler $handler): JsonResponse
    {
        $result = $handler->handle($request->all());

        return match ($result) {
            NotificationResult::InvalidSignature => response()->json(['message' => 'Signature tidak valid.'], 403),
            NotificationResult::AmountMismatch => response()->json(['message' => 'Nominal tidak cocok.'], 400),
            // Diproses atau tidak dikenal: balas 200 supaya Midtrans tidak mengirim ulang terus-menerus.
            default => response()->json(['message' => 'OK']),
        };
    }
}
