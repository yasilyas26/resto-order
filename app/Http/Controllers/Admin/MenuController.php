<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Menu;
use App\Models\OptionGroup;
use App\Services\Menu\LevelGroupBuilder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    public function index()
    {
        $menus = Menu::with(['category', 'optionGroups'])
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();

        return view('admin.menus.index', compact('menus'));
    }

    public function create()
    {
        return view('admin.menus.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('menus', 'public');
        }

        $menu = Menu::create($data);
        $this->syncOptionGroups($menu, $request->input('option_groups', []));
        $levels = $this->applyQuickLevels($menu, $request);

        return redirect()->route('admin.menus.index')
            ->with('status', 'Menu ditambahkan.'.$levels);
    }

    public function edit(Menu $menu)
    {
        return view('admin.menus.edit', $this->formData() + ['menu' => $menu]);
    }

    public function update(Request $request, Menu $menu)
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            // Foto lama tidak dirujuk lagi, jadi aman dibuang.
            $this->deleteImage($menu->image);
            $data['image'] = $request->file('image')->store('menus', 'public');
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($menu->image);
            $data['image'] = null;
        }

        $menu->update($data);
        $this->syncOptionGroups($menu, $request->input('option_groups', []));
        $levels = $this->applyQuickLevels($menu, $request);

        return redirect()->route('admin.menus.index')
            ->with('status', 'Menu diperbarui.'.$levels);
    }

    public function destroy(Menu $menu)
    {
        $menu->delete();

        return redirect()->route('admin.menus.index')
            ->with('status', 'Menu dipindah ke Sampah.');
    }

    private function formData(): array
    {
        return [
            'categories' => Category::with('optionGroups')->orderBy('sort')->orderBy('name')->get(),
            'optionGroups' => OptionGroup::with('options')->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category_id' => ['required', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'option_groups' => ['nullable', 'array'],
            'option_groups.*' => ['integer', Rule::exists('option_groups', 'id')->whereNull('deleted_at')],

            // "Level cepat": aturan ini hanya berlaku kalau kotak "tambahkan level" dicentang.
            'level_enabled' => ['nullable', 'boolean'],
            'level_group_name' => ['exclude_unless:level_enabled,1', 'required', 'string', 'max:100'],
            'level_prefix' => ['exclude_unless:level_enabled,1', 'required', 'string', 'max:50'],
            'level_from' => ['exclude_unless:level_enabled,1', 'required', 'integer', 'min:0', 'max:100'],
            'level_to' => [
                'exclude_unless:level_enabled,1', 'required', 'integer', 'min:0', 'max:100', 'gte:level_from',
                function (string $attribute, mixed $value, Closure $fail) use ($request) {
                    if (is_numeric($value) && ($value - (int) $request->input('level_from') + 1) > LevelGroupBuilder::MAX_LEVELS) {
                        $fail('Maksimal '.LevelGroupBuilder::MAX_LEVELS.' level sekali buat.');
                    }
                },
            ],
            'level_required' => ['nullable', 'boolean'],
            'level_surcharge_from' => ['exclude_unless:level_enabled,1', 'nullable', 'integer', 'min:0', 'max:100'],
            'level_surcharge' => ['exclude_unless:level_enabled,1', 'nullable', 'integer', 'min:0', 'max:1000000'],
        ], [
            'level_group_name.required' => 'Nama grup level wajib diisi.',
            'level_prefix.required' => 'Awalan nama level wajib diisi.',
            'level_from.required' => 'Isi level awal.',
            'level_to.required' => 'Isi level akhir.',
            'level_to.gte' => 'Level akhir tidak boleh lebih kecil dari level awal.',
        ]);

        $data['is_available'] = $request->boolean('is_available');
        $data['skip_category_options'] = $request->boolean('skip_category_options');

        // Bukan kolom menu: image, grup opsi, dan level cepat ditangani terpisah.
        foreach (array_keys($data) as $key) {
            if (str_starts_with($key, 'level_')) {
                unset($data[$key]);
            }
        }
        unset($data['image'], $data['option_groups']);

        return $data;
    }

    /** Membuat dan memasang deretan level (mis. Level 0-8) kalau diminta di form. Mengembalikan kalimat untuk pesan sukses. */
    private function applyQuickLevels(Menu $menu, Request $request): string
    {
        if (! $request->boolean('level_enabled')) {
            return '';
        }

        $from = (int) $request->input('level_from');
        $to = (int) $request->input('level_to');

        $group = app(LevelGroupBuilder::class)->apply($menu, [
            'group_name' => trim($request->input('level_group_name')),
            'prefix' => trim($request->input('level_prefix')),
            'from' => $from,
            'to' => $to,
            'required' => $request->boolean('level_required'),
            'surcharge_from' => $request->filled('level_surcharge_from') ? (int) $request->input('level_surcharge_from') : null,
            'surcharge' => (int) $request->input('level_surcharge', 0),
        ]);

        return " Level {$from}–{$to} dipasang lewat grup \"{$group->name}\".";
    }

    /**
     * Pasang grup opsi sesuai centang di form. Hanya grup aktif yang boleh dilepas dari menu;
     * pemasangan ke grup yang sedang di Sampah dibiarkan supaya tidak hilang diam-diam.
     */
    private function syncOptionGroups(Menu $menu, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $menu->optionGroups()->detach(OptionGroup::whereNotIn('id', $ids)->pluck('id')->all());

        $attach = [];
        foreach ($ids as $position => $id) {
            $attach[$id] = ['sort' => $position];
        }
        $menu->optionGroups()->syncWithoutDetaching($attach);
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
