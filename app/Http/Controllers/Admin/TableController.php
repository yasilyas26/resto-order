<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\KitchenStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Table;
use App\Support\QrSvg;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TableController extends Controller
{
    private const PER_PAGE = 8;

    /** Meja dianggap terisi selama order lunasnya belum selesai, plus sebentar setelah selesai (pelanggan masih makan). */
    private const OCCUPIED_AFTER_DONE_MINUTES = 45;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $tables = Table::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('number', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('code', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->orderBy('number')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $total = Table::count();
        $active = Table::where('is_active', true)->count();
        $occupiedIds = $this->occupiedTableIds();
        $occupied = Table::where('is_active', true)->whereIn('id', $occupiedIds)->count();

        return view('admin.tables.index', [
            'tables' => $tables,
            'search' => $search,
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
