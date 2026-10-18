<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Table;
use Illuminate\Database\Eloquent\Model;

class TrashController extends Controller
{
    /** Jenis data yang punya Sampah: slug di URL => [judul, model]. */
    private const TYPES = [
        'categories' => ['Kategori', Category::class],
        'menus' => ['Menu', Menu::class],
        'option-groups' => ['Grup opsi', OptionGroup::class],
        'options' => ['Opsi', Option::class],
        'tables' => ['Meja', Table::class],
    ];

    public function index()
    {
        $sections = [];

        foreach (self::TYPES as $slug => [$title, $model]) {
            $query = $model::onlyTrashed()->latest('deleted_at');

            // Eager load relasi yang dipakai aturan pulihkan/hapus permanen, supaya tidak N+1.
            match ($model) {
                Menu::class => $query->with('category'),
                Option::class => $query->with('group'),
                default => null,
            };

            $sections[] = ['slug' => $slug, 'title' => $title, 'items' => $query->get()];
        }

        return view('admin.trash.index', compact('sections'));
    }

    public function restore(string $type, int $id)
    {
        $item = $this->find($type, $id);

        if ($reason = $item->restoreBlocker()) {
            return back()->with('error', "Tidak bisa dipulihkan: {$reason}");
        }

        $item->restore();

        return back()->with('status', "\"{$item->trashLabel()}\" dipulihkan.");
    }

    public function forceDelete(string $type, int $id)
    {
        $item = $this->find($type, $id);

        if ($reason = $item->forceDeleteBlocker()) {
            return back()->with('error', "Tidak bisa dihapus permanen: {$reason}");
        }

        $label = $item->trashLabel();
        $item->forceDelete();

        return back()->with('status', "\"{$label}\" dihapus permanen.");
    }

    private function find(string $type, int $id): Model
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        $model = self::TYPES[$type][1];

        return $model::onlyTrashed()->findOrFail($id);
    }
}
