<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KitchenStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\BoardPresenter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ringkasan admin: angka hari ini + grafik omzet + papan dapur (hanya dilihat; aksinya tetap di papan karyawan). */
class DashboardController extends Controller
{
    /** Pilihan rentang grafik: kunci => [jumlah hari, satuan batang]. Rentang panjang dikelompokkan supaya batangnya tidak sesak. */
    public const RANGES = [
        '7d' => [7, 'day'],
        '14d' => [14, 'day'],
        '30d' => [30, 'day'],
        '3m' => [91, 'week'],
        '6m' => [182, 'week'],
        '1y' => [12, 'month'], // khusus bulan: jumlahnya bulan, bukan hari
    ];

    public const DEFAULT_RANGE = '14d';

    public function index(Request $request): View
    {
        return view('admin.index', [
            'initial' => $this->payload($this->range($request)),
            'ranges' => ['7d' => '7 Hari', '14d' => '2 Minggu', '30d' => '1 Bulan', '3m' => '3 Bulan', '6m' => '6 Bulan', '1y' => '1 Tahun'],
            'defaultRange' => self::DEFAULT_RANGE,
        ]);
    }

    /** Dipolling halaman Ringkasan tiap ~5 detik (?range= mengikuti pilihan grafik). */
    public function data(Request $request): JsonResponse
    {
        return response()->json($this->payload($this->range($request)));
    }

    private function range(Request $request): string
    {
        $range = $request->query('range');

        return is_string($range) && isset(self::RANGES[$range]) ? $range : self::DEFAULT_RANGE;
    }

    /** @return array{stats: array<string, mixed>, orders: list<array<string, mixed>>} */
    private function payload(string $range): array
    {
        return ['stats' => $this->stats($range), 'orders' => BoardPresenter::board()];
    }

    /** @return array<string, mixed> */
    private function stats(string $range): array
    {
        $paidToday = Order::where('payment_status', PaymentStatus::Paid)->whereDate('paid_at', today());

        // Omzet = uang masuk hari ini dikurangi refund (item habis yang sudah dikembalikan/akan dikembalikan).
        $revenue = (int) (clone $paidToday)->sum('total') - (int) (clone $paidToday)->sum('refund_amount');

        $done = Order::where('payment_status', PaymentStatus::Paid)
            ->where('kitchen_status', KitchenStatus::Done)
            ->whereDate('completed_at', today());

        $cookSeconds = (clone $done)->whereNotNull('cooking_started_at')->get(['cooking_started_at', 'completed_at'])
            ->map(fn (Order $o) => $o->cooking_started_at->diffInSeconds($o->completed_at, true));

        return [
            'revenue' => $revenue,
            'completed' => $done->count(),
            'avgCookMinutes' => $cookSeconds->isEmpty() ? null : round($cookSeconds->avg() / 60, 1),
            'updatedAt' => now()->format('H:i:s').' WIB',
            'range' => $range,
            'unit' => self::RANGES[$range][1],
            'series' => $this->revenueSeries($range),
        ];
    }

    /**
     * Omzet per batang (hari, minggu, atau bulan) sampai hari ini. Batang tanpa order tetap tampil 0
     * supaya sumbu waktunya utuh. Omzet = total dikurangi refund, hanya order lunas.
     *
     * @return list<array{label: string, full: string, revenue: int}>
     */
    private function revenueSeries(string $range): array
    {
        [$count, $unit] = self::RANGES[$range];
        $buckets = $this->buckets($count, $unit);

        $byDay = Order::where('payment_status', PaymentStatus::Paid)
            ->where('paid_at', '>=', $buckets[0]['from'])
            ->selectRaw('DATE(paid_at) as day, SUM(total - refund_amount) as revenue')
            ->groupBy('day')
            ->pluck('revenue', 'day');

        return array_map(function (array $bucket) use ($byDay) {
            $sum = 0;
            for ($d = $bucket['from']->copy(); $d->lte($bucket['to']); $d->addDay()) {
                $sum += (int) ($byDay[$d->toDateString()] ?? 0);
            }

            return ['label' => $bucket['label'], 'full' => $bucket['full'], 'revenue' => $sum];
        }, $buckets);
    }

    /** @return list<array{from: Carbon, to: Carbon, label: string, full: string}> */
    private function buckets(int $count, string $unit): array
    {
        $buckets = [];

        if ($unit === 'month') {
            for ($i = $count - 1; $i >= 0; $i--) {
                $start = today()->startOfMonth()->subMonths($i);
                $buckets[] = [
                    'from' => $start,
                    'to' => $start->copy()->endOfMonth()->startOfDay(),
                    'label' => $start->translatedFormat('M y'),
                    'full' => $start->translatedFormat('F Y'),
                ];
            }

            return $buckets;
        }

        $size = $unit === 'week' ? 7 : 1;
        $n = (int) ceil($count / $size);
        $start = today()->subDays($n * $size - 1);

        for ($i = 0; $i < $n; $i++) {
            $from = $start->copy()->addDays($i * $size);
            $to = $from->copy()->addDays($size - 1);
            $buckets[] = [
                'from' => $from,
                'to' => $to,
                'label' => $from->translatedFormat('d M'),
                'full' => $size === 1 ? $from->translatedFormat('l, d M Y') : $from->translatedFormat('d M').' – '.$to->translatedFormat('d M Y'),
            ];
        }

        return $buckets;
    }
}
