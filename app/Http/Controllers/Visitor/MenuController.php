<?php

namespace App\Http\Controllers\Visitor;

use App\Http\Controllers\Controller;
use App\Support\MenuCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(Request $request): View
    {
        $categories = MenuCatalog::categories();

        return view('visitor.menu', [
            'table' => $request->attributes->get('table'),
            'categories' => $categories,
            'menusForScript' => MenuCatalog::forScript($categories),
        ]);
    }

    /** Keranjang dan form checkout. Isi keranjang dibaca Alpine dari localStorage. */
    public function cart(Request $request): View
    {
        return view('visitor.cart', [
            'table' => $request->attributes->get('table'),
            'menusForScript' => MenuCatalog::forScript(MenuCatalog::categories()),
            'snapUrl' => config('midtrans.snap_js_url'),
            'clientKey' => config('midtrans.client_key'),
        ]);
    }
}
