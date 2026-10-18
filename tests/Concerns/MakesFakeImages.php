<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;

trait MakesFakeImages
{
    /**
     * Gambar PNG 1x1 asli. UploadedFile::fake()->image() butuh ekstensi GD,
     * sedangkan tes ini tidak perlu GD (cukup file yang terbaca sebagai gambar).
     *
     * @param  int  $paddingKb  tambahan ukuran file, untuk menguji batas maksimal upload
     */
    protected function fakeImage(string $name = 'foto.png', int $paddingKb = 0): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $png.str_repeat("\0", $paddingKb * 1024));
    }
}
