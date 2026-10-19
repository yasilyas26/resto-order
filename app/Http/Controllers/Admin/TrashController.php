<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Trash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class TrashController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request)
    {
        $tab = array_key_exists($request->query('tab'), Trash::TABS) ? $request->query('tab') : 'semua';

        $all = Trash::items($tab);
        $page = max(1, $request->integer('page', 1));
        $items = new LengthAwarePaginator($all->forPage($page, self::PER_PAGE)->values(), $all->count(), self::PER_PAGE, $page, [
            'path' => $request->url(),
            'query' => ['tab' => $tab],
        ]);

        return view('admin.trash.index', [
            'tab' => $tab,
            'tabs' => Trash::TABS,
            'counts' => Trash::counts(),
            'items' => $items,
        ]);
    }

    /** Kosongkan semua: yang masih dirujuk data lain tetap tinggal di Sampah. */
    public function empty()
    {
        $result = Trash::purge();

        $message = "{$result['deleted']} data dihapus permanen.";

        if ($result['skipped'] > 0) {
            return back()->with('status', "{$message} {$result['skipped']} data dilewati karena masih dirujuk data lain.");
        }

        return back()->with('status', $message);
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
        abort_unless(isset(Trash::TYPES[$type]), 404);

        $model = Trash::TYPES[$type][1];

        return $model::onlyTrashed()->findOrFail($id);
    }
}
