<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OptionGroup;
use App\Services\Menu\LevelGroupBuilder;
use Closure;
use Illuminate\Http\Request;

class OptionGroupController extends Controller
{
    public function index()
    {
        $groups = OptionGroup::withCount('options')->orderBy('name')->get();

        return view('admin.option-groups.index', compact('groups'));
    }

    public function create()
    {
        return view('admin.option-groups.create');
    }

    public function store(Request $request)
    {
        $group = OptionGroup::create($this->validated($request));

        // Grup kosong belum berguna, jadi langsung ke halaman untuk mengisi opsinya.
        return redirect()->route('admin.option-groups.edit', $group)
            ->with('status', 'Grup dibuat. Sekarang tambahkan opsinya.');
    }

    /** Pembuat level cepat: satu grup berisi deretan level (mis. Level Pedas 0 sampai 8), lalu diedit di halaman grup. */
    public function storeLevels(Request $request, LevelGroupBuilder $builder)
    {
        $data = $request->validate([
            'group_name' => ['required', 'string', 'max:100'],
            'prefix' => ['required', 'string', 'max:50'],
            'from' => ['required', 'integer', 'min:0', 'max:100'],
            'to' => [
                'required', 'integer', 'min:0', 'max:100', 'gte:from',
                function (string $attribute, mixed $value, Closure $fail) use ($request) {
                    if (is_numeric($value) && ($value - (int) $request->input('from') + 1) > LevelGroupBuilder::MAX_LEVELS) {
                        $fail('Maksimal '.LevelGroupBuilder::MAX_LEVELS.' level sekali buat.');
                    }
                },
            ],
            'required' => ['nullable', 'boolean'],
            'surcharge_from' => ['nullable', 'integer', 'min:0', 'max:100'],
            'surcharge' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ], [
            'group_name.required' => 'Nama grup wajib diisi.',
            'prefix.required' => 'Awalan nama level wajib diisi.',
            'from.required' => 'Isi level awal.',
            'to.required' => 'Isi level akhir.',
            'to.gte' => 'Level akhir tidak boleh lebih kecil dari level awal.',
        ]);

        $group = $builder->build([
            'group_name' => trim($data['group_name']),
            'prefix' => trim($data['prefix']),
            'from' => (int) $data['from'],
            'to' => (int) $data['to'],
            'required' => $request->boolean('required'),
            'surcharge_from' => $request->filled('surcharge_from') ? (int) $data['surcharge_from'] : null,
            'surcharge' => (int) ($data['surcharge'] ?? 0),
        ]);

        return redirect()->route('admin.option-groups.edit', $group)
            ->with('status', "Grup \"{$group->name}\" siap: level {$data['from']}–{$data['to']} dibuat. Atur harga atau ubah di sini, lalu pasang ke kategori atau menu.");
    }

    public function edit(OptionGroup $optionGroup)
    {
        $optionGroup->load('options');

        return view('admin.option-groups.edit', ['group' => $optionGroup]);
    }

    public function update(Request $request, OptionGroup $optionGroup)
    {
        $optionGroup->update($this->validated($request));

        return redirect()->route('admin.option-groups.edit', $optionGroup)
            ->with('status', 'Grup diperbarui.');
    }

    public function destroy(OptionGroup $optionGroup)
    {
        $optionGroup->delete();

        return redirect()->route('admin.option-groups.index')
            ->with('status', 'Grup opsi dipindah ke Sampah.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'min_select' => ['nullable', 'integer', 'min:0', 'max:20'],
            'max_select' => ['required', 'integer', 'min:1', 'max:20', 'gte:min_select'],
        ]);

        $data['min_select'] ??= 0;

        return $data;
    }
}
