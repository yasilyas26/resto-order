<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Menu;
use App\Models\OptionGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class MenuController extends Controller
{
    private const PER_PAGE = 8;

    /** Satu halaman, tiga tab: Menu, Kategori, Grup Opsi (?tab=menu|kategori|opsi). */
    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['kategori', 'opsi'], true) ? $request->query('tab') : 'menu';
        $data = ['tab' => $tab];

        if ($tab === 'kategori') {
            $data['categories'] = Category::with('optionGroups')->withCount('menus')->orderBy('sort')->orderBy('name')->get();
        } elseif ($tab === 'opsi') {
            $data['groups'] = OptionGroup::withCount('options')->orderBy('name')->get();
        } else {
            $search = trim((string) $request->query('q', ''));
            $categoryId = $request->integer('category') ?: null;
            $status = in_array($request->query('status'), ['available', 'sold_out'], true) ? $request->query('status') : null;

            $data['menus'] = Menu::with(['category.optionGroups', 'optionGroups'])
                ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
                ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
                ->when($status, fn ($q) => $q->where('is_available', $status === 'available'))
                ->orderBy('category_id')
                ->orderBy('name')
                ->paginate(self::PER_PAGE)
                ->withQueryString();
            $data['categoryOptions'] = Category::orderBy('sort')->orderBy('name')->get(['id', 'name']);
            $data += ['search' => $search, 'categoryId' => $categoryId, 'status' => $status];
        }

        return view('admin.menus.index', $data);
    }

    /** Sakelar Tersedia/Habis langsung dari tabel. */
    public function toggleAvailability(Menu $menu)
    {
        $menu->update(['is_available' => ! $menu->is_available]);

        return back()->with('status', "\"{$menu->name}\" sekarang ".($menu->is_available ? 'tersedia.' : 'habis.'));
    }

    public function create()
    {
        return view('admin.menus.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $newImage = $request->hasFile('image') ? $request->file('image')->store('menus', 'public') : null;

        if ($newImage) {
            $data['image'] = $newImage;
        }

        try {
            // Menu dan pemasangan grup opsinya satu paket: gagal di tengah berarti tidak ada yang tersimpan.
            DB::transaction(function () use ($data, $request) {
                $menu = Menu::create($data);
                $this->syncOptionGroups($menu, $request->input('option_groups', []));
            });
        } catch (Throwable $e) {
            // Foto sudah terlanjur di storage (file tidak ikut transaksi), jadi dibuang supaya tidak yatim.
            $this->deleteImage($newImage);

            throw $e;
        }

        return redirect()->route('admin.menus.index')
            ->with('status', 'Menu ditambahkan.');
    }

    public function edit(Menu $menu)
    {
        return view('admin.menus.edit', $this->formData() + ['menu' => $menu]);
    }

    public function update(Request $request, Menu $menu)
    {
        $data = $this->validated($request);
        $oldImage = $menu->image;
        $newImage = $request->hasFile('image') ? $request->file('image')->store('menus', 'public') : null;

        if ($newImage) {
            $data['image'] = $newImage;
        } elseif ($request->boolean('remove_image')) {
            $data['image'] = null;
        }

        try {
            DB::transaction(function () use ($menu, $data, $request) {
                $menu->update($data);
                $this->syncOptionGroups($menu, $request->input('option_groups', []));
            });
        } catch (Throwable $e) {
            // Gagal: foto baru dibuang dan foto lama tetap utuh (masih dirujuk database).
            $this->deleteImage($newImage);

            throw $e;
        }

        // Foto lama baru dibuang setelah database berhasil, supaya tidak ada menu yang kehilangan fotonya.
        if (array_key_exists('image', $data) && $oldImage !== $menu->image) {
            $this->deleteImage($oldImage);
        }

        return redirect()->route('admin.menus.index')
            ->with('status', 'Menu diperbarui.');
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

        ]);

        $data['is_available'] = $request->boolean('is_available');
        $data['skip_category_options'] = $request->boolean('skip_category_options');

        // Bukan kolom menu: image dan grup opsi ditangani terpisah.
        unset($data['image'], $data['option_groups']);

        return $data;
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
