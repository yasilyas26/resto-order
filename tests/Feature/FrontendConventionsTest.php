<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Aturan penulisan template yang pernah menyebabkan bug diam-diam. Diperiksa secara statis, karena
 * bug seperti ini baru kelihatan kalau tombolnya diklik di browser.
 */
class FrontendConventionsTest extends TestCase
{
    /**
     * Alpine mengubah hasil `undefined` dari ekspresi bertitik/berkurung (mis. busy[order.code]) menjadi string kosong,
     * dan atribut disabled="" justru MENONAKTIFKAN tombol. Jadi :disabled harus selalu menghasilkan boolean,
     * bukan sebuah path properti mentah.
     */
    public function test_disabled_bindings_never_use_a_bare_property_path(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            foreach (preg_split('/\R/', $file->getContents()) as $number => $line) {
                if (preg_match_all('/:disabled="([^"]*)"/', $line, $matches)) {
                    foreach ($matches[1] as $expression) {
                        $isBarePath = preg_match('/^[\w$]+(\.[\w$]+|\[[^\]]+\])+$/', trim($expression));

                        if ($isBarePath) {
                            $offenders[] = $file->getRelativePathname().':'.($number + 1)." :disabled=\"{$expression}\"";
                        }
                    }
                }
            }
        }

        $this->assertSame([], $offenders, "Bungkus dengan !! supaya hasilnya boolean:\n".implode("\n", $offenders));
    }

    /** Ikon dengan class ukuran sendiri tidak boleh ikut menerima ukuran bawaan (dua kelas ukuran bertabrakan). */
    public function test_icon_default_size_is_only_applied_without_a_class(): void
    {
        $html = view('components.icon', ['name' => 'plus', 'attributes' => new \Illuminate\View\ComponentAttributeBag(['class' => 'h-12 w-12'])])->render();

        $this->assertStringContainsString('h-12 w-12', $html);
        $this->assertStringNotContainsString('h-5 w-5', $html);

        $plain = view('components.icon', ['name' => 'plus', 'attributes' => new \Illuminate\View\ComponentAttributeBag([])])->render();
        $this->assertStringContainsString('h-5 w-5', $plain);
    }
}
