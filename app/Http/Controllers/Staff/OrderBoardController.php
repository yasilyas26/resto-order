<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Kitchen\OrderWorkflow;
use App\Services\Kitchen\WorkflowException;
use App\Support\BoardPresenter;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Papan order untuk karyawan. Hanya order yang sudah dibayar (dikonfirmasi webhook) yang tampil di sini (PRD K-02).
 */
class OrderBoardController extends Controller
{
    public function index(): View
    {
        return view('staff.orders', ['initial' => $this->payload()]);
    }

    /** Dipolling papan tiap beberapa detik (PRD K-05). */
    public function data(): JsonResponse
    {
        return response()->json($this->payload());
    }

    public function start(Order $order, OrderWorkflow $workflow): JsonResponse
    {
        return $this->act(fn () => $workflow->start($order));
    }

    public function complete(Order $order, OrderWorkflow $workflow): JsonResponse
    {
        return $this->act(fn () => $workflow->complete($order));
    }

    public function serve(Request $request, Order $order, OrderItem $item, OrderWorkflow $workflow): JsonResponse
    {
        $request->validate(['served' => ['required', 'boolean']]);

        return $this->act(fn () => $workflow->setServed($order, $item, $request->boolean('served')));
    }

    public function unavailable(Request $request, Order $order, OrderItem $item, OrderWorkflow $workflow): JsonResponse
    {
        $request->validate(['unavailable' => ['required', 'boolean']]);

        return $this->act(fn () => $workflow->setUnavailable($order, $item, $request->boolean('unavailable')));
    }

    /** Menjalankan aksi alur dapur dan membalas dengan kartu terbaru, atau 409 dengan alasan yang bisa dibaca. */
    private function act(Closure $action): JsonResponse
    {
        try {
            $order = $action();
        } catch (WorkflowException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['order' => BoardPresenter::card($order, BoardPresenter::rankOf($order))]);
    }

    /** @return array{orders: list<array<string, mixed>>, serverTime: string} */
    private function payload(): array
    {
        return [
            'orders' => BoardPresenter::board(),
            'serverTime' => now()->toIso8601String(),
        ];
    }
}
