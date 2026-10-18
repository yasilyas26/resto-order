<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Option;
use App\Models\OptionGroup;
use Illuminate\Http\Request;

class OptionController extends Controller
{
    public function store(Request $request, OptionGroup $optionGroup)
    {
        $optionGroup->options()->create($this->validated($request));

        return redirect()->route('admin.option-groups.edit', $optionGroup)
            ->with('status', 'Opsi ditambahkan.');
    }

    public function update(Request $request, Option $option)
    {
        $option->update($this->validated($request));

        return redirect()->route('admin.option-groups.edit', $option->option_group_id)
            ->with('status', 'Opsi diperbarui.');
    }

    public function destroy(Option $option)
    {
        $option->delete();

        return redirect()->route('admin.option-groups.edit', $option->option_group_id)
            ->with('status', 'Opsi dipindah ke Sampah.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'extra_price' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['extra_price'] ??= 0;
        $data['sort'] ??= 0;
        $data['is_available'] = $request->boolean('is_available');

        return $data;
    }
}
