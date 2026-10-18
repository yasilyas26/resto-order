<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\OptionGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('optionGroups')->orderBy('sort')->orderBy('name')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $category = Category::create($data);
        $this->syncOptionGroups($category, $request->input('option_groups', []));

        return redirect()->route('admin.categories.index')
            ->with('status', 'Kategori ditambahkan.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.edit', $this->formData() + ['category' => $category]);
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validated($request, $category));
        $this->syncOptionGroups($category, $request->input('option_groups', []));

        return redirect()->route('admin.categories.index')
            ->with('status', 'Kategori diperbarui.');
    }

    public function destroy(Category $category)
    {
        if ($category->menus()->exists()) {
            return redirect()->route('admin.categories.index')
                ->with('error', "Kategori \"{$category->name}\" masih punya menu aktif. Pindahkan atau hapus menunya dulu.");
        }

        $category->delete();

        return redirect()->route('admin.categories.index')
            ->with('status', 'Kategori dipindah ke Sampah.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($category),
            ],
            'sort' => ['nullable', 'integer', 'min:0'],
            'option_groups' => ['nullable', 'array'],
            'option_groups.*' => ['integer', Rule::exists('option_groups', 'id')->whereNull('deleted_at')],
        ]);

        $data['sort'] ??= 0;
        unset($data['option_groups']);

        return $data;
    }

    private function formData(): array
    {
        return ['optionGroups' => OptionGroup::with('options')->orderBy('name')->get()];
    }

    /** Sama seperti di menu: hanya grup aktif yang bisa dilepas, pemasangan ke grup di Sampah dibiarkan. */
    private function syncOptionGroups(Category $category, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $category->optionGroups()->detach(OptionGroup::whereNotIn('id', $ids)->pluck('id')->all());

        $attach = [];
        foreach ($ids as $position => $id) {
            $attach[$id] = ['sort' => $position];
        }
        $category->optionGroups()->syncWithoutDetaching($attach);
    }
}
