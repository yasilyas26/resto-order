<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\KitchenStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Table;
use App\Support\QrSvg;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TableController extends Controller
{
    private const PER_PAGE = 8;

    /** Meja dianggap terisi selama order lunasnya belum selesai, plus sebentar setelah selesai (pelanggan masih makan). */
    private const OCCUPIED_AFTER_DONE_MINUTES = 45;

    /** Pilihan "Urutkan": kunci di URL => tulisan di dropdown. Yang pertama adalah bawaan. */
    public const SORTS = [
        'number_asc' => 'Nomor: kecil ke besar',
        'number_desc' => 'Nomor: besar ke kecil',
        'newest' => 'Terbaru ditambahkan',
        'oldest' => 'Terlama ditambahkan',
        'occupied' => 'Terisi dulu',
        'active' => 'Aktif dulu',
        'inactive' => 'Nonaktif dulu',
    ];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $sort = array_key_exists((string) $request->query('sort'), self::SORTS) ? $request->query('sort') : array_key_first(self::SORTS);
        $occupiedIds = $this->occupiedTableIds();

        $query = Table::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('number', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('code', 'like', '%'.addcslashes($search, '%_\\').'%')));

        $this->applySort($query, $sort, $occupiedIds);

        $tables = $query->paginate(self::PER_PAGE)->withQueryString();

        $total = Table::count();
        $active = Table::where('is_active', true)->count();
        $occupied = Table::where('is_active', true)->whereIn('id', $occupiedIds)->count();

        return view('admin.tables.index', [
            'tables' => $tables,
            'search' => $search,
            'sort' => $sort,
            'sorts' => self::SORTS,
            'occupiedIds' => $occupiedIds,
            'stats' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $total - $active,
                'occupied' => $occupied,
                'percent' => $active > 0 ? (int) round($occupied / $active * 100) : 0,
            ],
        ]);
    }

    /**
     * Nomor meja berupa teks ("10" lebih kecil dari "9" kalau diurut biasa), jadi diurutkan sebagai angka dulu,
     * lalu sebagai teks untuk nomor seperti "A7". Urutan terakhir selalu nomor supaya hasilnya stabil antar halaman.
     *
     * @param  Builder<Table>  $query
     * @param  list<int>  $occupiedIds
     */
    private function applySort(Builder $query, string $sort, array $occupiedIds): void
    {
        // Nomor bukan angka (mis. "A7", "VIP") selalu di belakang nomor angka, baik naik maupun turun.
        $byNumber = fn (string $dir) => $query
            ->orderByRaw("CASE WHEN CAST(number AS UNSIGNED) = 0 AND number NOT LIKE '0%' THEN 1 ELSE 0 END")
            ->orderByRaw("CAST(number AS UNSIGNED) {$dir}")
            ->orderBy('number', $dir);

        match ($sort) {
            'number_desc' => $byNumber('desc'),
            'newest' => $query->orderByDesc('id'),
            'oldest' => $query->orderBy('id'),
            'active' => $query->orderByDesc('is_active')->tap(fn () => $byNumber('asc')),
            'inactive' => $query->orderBy('is_active')->tap(fn () => $byNumber('asc')),
            'occupied' => $this->orderOccupiedFirst($query, $occupiedIds)->tap(fn () => $byNumber('asc')),
            default => $byNumber('asc'),
        };
    }

    /**
     * @param  Builder<Table>  $query
     * @param  list<int>  $ids
     * @return Builder<Table>
     */
    private function orderOccupiedFirst(Builder $query, array $ids): Builder
    {
        if ($ids === []) {
            return $query;
        }

        $marks = implode(',', array_fill(0, count($ids), '?'));

        return $query->orderByRaw("CASE WHEN is_active = 1 AND id IN ({$marks}) THEN 0 ELSE 1 END", $ids);
    }

    /** @return list<int> */
    private function occupiedTableIds(): array
    {
        return Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('order_type', OrderType::DineIn)
            ->whereNotNull('table_id')
            ->where(fn ($q) => $q
                ->where('kitchen_status', '!=', KitchenStatus::Done)
                ->orWhere('completed_at', '>=', now()->subMinutes(self::OCCUPIED_AFTER_DONE_MINUTES)))
            ->distinct()
            ->pluck('table_id')
            ->all();
    }

    public function create()
    {
        return view('admin.tables.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $table = Table::create($data);

        return redirect()->route('admin.tables.index')
            ->with('status', "Meja {$table->number} ditambahkan dengan kode {$table->code}.");
    }

    public function edit(Table $table)
    {
        return view('admin.tables.edit', compact('table'));
    }

    public function update(Request $request, Table $table)
    {
        $table->update($this->validated($request, $table));

        return redirect()->route('admin.tables.index')
            ->with('status', 'Meja diperbarui.');
    }

    public function destroy(Table $table)
    {
        $table->delete();

        return redirect()->route('admin.tables.index')
            ->with('status', "Meja {$table->number} dipindah ke Sampah. QR-nya tidak berlaku lagi.");
    }

    public function regenerate(Table $table)
    {
        $table->code = Table::generateCode($table->number);
        $table->save();

        return redirect()->route('admin.tables.index')
            ->with('status', "Kode meja {$table->number} diganti jadi {$table->code}. QR dan stiker lama tidak berlaku lagi, cetak ulang.");
    }

    public function qr(Table $table)
    {
        return view('admin.tables.qr', [
            'table' => $table,
            'svg' => QrSvg::make($table->scanUrl()),
        ]);
    }

    public function qrDownload(Table $table)
    {
        return response(QrSvg::make($table->scanUrl(), 600), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qr-meja-'.$table->code.'.svg"',
        ]);
    }

    /** Unduh QR beberapa meja sekaligus: satu meja jadi file SVG, lebih dari satu dibungkus ZIP. */
    public function qrDownloadMany(Request $request)
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ], [
            'ids.required' => 'Pilih dulu meja yang QR-nya mau diunduh.',
            'ids.min' => 'Pilih dulu meja yang QR-nya mau diunduh.',
        ])['ids'];

        $tables = Table::whereIn('id', $ids)->orderBy('id')->get();
        abort_if($tables->isEmpty(), 404);

        if ($tables->count() === 1) {
            return $this->qrDownload($tables->first());
        }

        $path = tempnam(sys_get_temp_dir(), 'qr');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        foreach ($tables as $table) {
            $zip->addFromString('qr-meja-'.$table->code.'.svg', QrSvg::make($table->scanUrl(), 600));
        }
        $zip->close();

        return response()->download($path, 'qr-meja-'.now()->format('Ymd-His').'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    private function validated(Request $request, ?Table $table = null): array
    {
        $data = $request->validate([
            'number' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('tables', 'number')
                    ->whereNull('deleted_at')
                    ->ignore($table),
            ],
        ], [
            'number.regex' => 'Nomor meja hanya boleh huruf, angka, dan tanda minus.',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
