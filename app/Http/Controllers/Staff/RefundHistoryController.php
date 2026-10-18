<?php

namespace App\Http\Controllers\Staff;

use App\Enums\KitchenStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Kitchen\OrderWorkflow;
use App\Services\Kitchen\WorkflowException;
use App\Support\BoardPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Tab "Riwayat & Refund": order selesai, dan refund yang perlu atau sudah diserahkan kasir (PRD K-07, K-08). */
class RefundHistoryController extends Controller
{
    private const PER_PAGE = 15;

    private const FILTERS = ['refund_needed', 'refunded', 'done'];

    public function index(): View
    {
        $counts = $this->counts();
        $filter = $counts['refund_needed'] > 0 ? 'refund_needed' : 'done';

        return view('staff.history', [
            'filter' => $filter,
            'initial' => $this->page($filter, 1) + ['counts' => $counts],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter' => ['required', 'in:'.implode(',', self::FILTERS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($this->page($data['filter'], (int) ($data['page'] ?? 1)) + ['counts' => $this->counts()]);
    }

    public function refund(Request $request, Order $order, OrderWorkflow $workflow): JsonResponse
    {
        try {
            $order = $workflow->markRefunded($order, $request->user());
        } catch (WorkflowException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['order' => BoardPresenter::historyRow($order->load('refundedBy')), 'counts' => $this->counts()]);
    }

    /** @return array{orders: list<array<string, mixed>>, nextPage: ?int} */
    private function page(string $filter, int $page): array
    {
        $query = $this->query($filter)->with(['items', 'table', 'refundedBy']);

        match ($filter) {
            'refund_needed' => $query->orderBy('paid_at')->orderBy('id'), // yang paling lama menunggu dulu
            'refunded' => $query->orderByDesc('refunded_at')->orderByDesc('id'),
            default => $query->orderByDesc('completed_at')->orderByDesc('id'),
        };

        $paginator = $query->paginate(self::PER_PAGE, page: $page);

        return [
            'orders' => $paginator->getCollection()->map(fn (Order $order) => BoardPresenter::historyRow($order))->all(),
            'nextPage' => $paginator->hasMorePages() ? $page + 1 : null,
        ];
    }

    /** @return array{refund_needed: int, refunded: int, done: int} */
    private function counts(): array
    {
        return [
            'refund_needed' => $this->query('refund_needed')->count(),
            'refunded' => $this->query('refunded')->count(),
            'done' => $this->query('done')->count(),
        ];
    }

    /** @return Builder<Order> */
    private function query(string $filter): Builder
    {
        $paid = Order::query()->where('payment_status', PaymentStatus::Paid);

        return match ($filter) {
            'refund_needed' => $paid->where('refund_status', RefundStatus::Needed),
            'refunded' => $paid->where('refund_status', RefundStatus::Refunded),
            default => $paid->where('kitchen_status', KitchenStatus::Done),
        };
    }
}
