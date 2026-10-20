<?php

namespace App\Http\Controllers\Visitor;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderHistoryController extends Controller
{
    private const PER_PAGE = 10;

    /** Riwayat pelanggan yang login, dari database. Tamu tidak punya riwayat (route ini butuh login). */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('visitor.history', [
            'user' => $user,
            'first' => $this->page($user->id, 1),
            'paidCount' => Order::where('user_id', $user->id)->where('payment_status', PaymentStatus::Paid)->count(),
        ]);
    }

    /** Halaman berikutnya riwayat pelanggan ("Muat lebih banyak"). */
    public function data(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));

        return response()->json($this->page($request->user()->id, $page));
    }

    /**
     * Menyegarkan order yang sedang tampil di riwayat. Hanya order milik akun ini yang dikembalikan,
     * kode order milik orang lain diabaikan. whereIn() memakai parameter, jadi aman dari injeksi.
     */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codes' => ['required', 'array', 'min:1', 'max:30'],
            'codes.*' => ['string', 'max:20'],
        ]);

        $orders = Order::with(['items.options', 'table'])
            ->where('user_id', $request->user()->id)
            ->whereIn('code', $data['codes'])
            ->latest()
            ->latest('id')
            ->get();

        return response()->json([
            'orders' => $orders->map->toSummary()->all(),
        ]);
    }

    /** @return array{orders: list<array<string, mixed>>, nextPage: ?int} */
    private function page(int $userId, int $page): array
    {
        $paginator = Order::with(['items.options', 'table'])
            ->where('user_id', $userId)
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE, page: $page);

        return [
            'orders' => $paginator->getCollection()->map->toSummary()->all(),
            'nextPage' => $paginator->hasMorePages() ? $page + 1 : null,
        ];
    }
}
