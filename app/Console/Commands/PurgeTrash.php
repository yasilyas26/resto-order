<?php

namespace App\Console\Commands;

use App\Support\Trash;
use Illuminate\Console\Command;

class PurgeTrash extends Command
{
    protected $signature = 'trash:purge';

    protected $description = 'Hapus permanen data di Sampah yang sudah lewat masa simpan (30 hari). Yang masih dirujuk data lain dilewati.';

    public function handle(): int
    {
        $result = Trash::purge(Trash::RETENTION_DAYS);

        $this->info("{$result['deleted']} data dihapus permanen, {$result['skipped']} dilewati karena masih dirujuk.");

        return self::SUCCESS;
    }
}
