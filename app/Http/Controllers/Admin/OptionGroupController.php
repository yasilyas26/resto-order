<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OptionGroup;
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
