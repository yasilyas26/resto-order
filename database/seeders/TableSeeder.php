<?php

namespace Database\Seeders;

use App\Models\Table;
use Illuminate\Database\Seeder;

/** Meja nomor 1 sampai 40. Aman dijalankan berulang: nomor yang sudah ada (termasuk di Sampah) dilewati. */
class TableSeeder extends Seeder
{
    public const COUNT = 40;

    public function run(): void
    {
        $existing = Table::withTrashed()->pluck('number')->all();

        foreach (range(1, self::COUNT) as $number) {
            if (! in_array((string) $number, $existing, true)) {
                // Kode unik untuk QR dibuat otomatis oleh model.
                Table::create(['number' => (string) $number]);
            }
        }
    }
}
