<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Support\QrSvg;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TableController extends Controller
{
    public function index()
    {
        $tables = Table::orderBy('number')->get();

        return view('admin.tables.index', compact('tables'));
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
