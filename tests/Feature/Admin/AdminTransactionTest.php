<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Menu;
use App\Models\OptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/** Menu dan kategori ditulis ke beberapa tabel sekaligus: kalau satu langkah gagal, tidak ada yang tersimpan setengah. */
class AdminTransactionTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private OptionGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $this->category = Category::create(['name' => 'Mie']);
        $this->group = OptionGroup::create(['name' => 'Level Pedas', 'min_select' => 1, 'max_select' => 1]);
    }

    /** Membuat langkah memasang grup (insert ke tabel pivot) gagal, seolah database bermasalah di tengah jalan. */
    private function breakPivotInsert(string $table): void
    {
        DB::beforeExecuting(function (string $query) use ($table) {
            if (str_starts_with(strtolower($query), 'insert') && str_contains($query, $table)) {
                throw new RuntimeException('database bermasalah');
            }
        });
    }

    /** PNG 1x1 yang asli, karena PHP di sini tidak punya ekstensi GD untuk membuat gambar palsu. */
    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='),
        );
    }

    private function menuPayload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'name' => 'MieGacoy',
            'price' => 15000,
            'is_available' => '1',
            'option_groups' => [$this->group->id],
        ], $overrides);
    }

    public function test_a_failed_group_attachment_leaves_no_menu_and_no_orphan_photo_behind(): void
    {
        $this->breakPivotInsert('menu_option_group');

        $this->withoutExceptionHandling();

        try {
            $this->post('/admin/menus', $this->menuPayload(['image' => $this->png('mie.png')]));
            $this->fail('Seharusnya gagal.');
        } catch (RuntimeException) {
            // diharapkan
        }

        $this->assertDatabaseCount('menus', 0);
        $this->assertSame([], Storage::disk('public')->allFiles(), 'foto yang sudah terlanjur diunggah ikut dibuang');
    }

    public function test_a_failed_update_keeps_the_old_data_and_the_old_photo(): void
    {
        Storage::disk('public')->put('menus/lama.jpg', 'foto lama');
        $menu = Menu::create(['category_id' => $this->category->id, 'name' => 'Mie Lama', 'price' => 10000, 'image' => 'menus/lama.jpg', 'is_available' => true]);

        $this->breakPivotInsert('menu_option_group');
        $this->withoutExceptionHandling();

        try {
            $this->put("/admin/menus/{$menu->id}", $this->menuPayload(['name' => 'Mie Baru', 'image' => $this->png('baru.png')]));
            $this->fail('Seharusnya gagal.');
        } catch (RuntimeException) {
            // diharapkan
        }

        $menu->refresh();
        $this->assertSame('Mie Lama', $menu->name, 'perubahan nama dibatalkan');
        $this->assertSame('menus/lama.jpg', $menu->image);
        $this->assertSame(['menus/lama.jpg'], Storage::disk('public')->allFiles(), 'foto lama utuh, foto baru dibuang');
    }

    public function test_a_successful_update_replaces_the_photo_and_removes_the_old_one(): void
    {
        Storage::disk('public')->put('menus/lama.jpg', 'foto lama');
        $menu = Menu::create(['category_id' => $this->category->id, 'name' => 'Mie Lama', 'price' => 10000, 'image' => 'menus/lama.jpg', 'is_available' => true]);

        $this->put("/admin/menus/{$menu->id}", $this->menuPayload(['image' => $this->png('baru.png')]))->assertRedirect('/admin/menus');

        $files = Storage::disk('public')->allFiles();
        $this->assertCount(1, $files);
        $this->assertNotSame('menus/lama.jpg', $files[0]);
        $this->assertSame($files[0], $menu->fresh()->image);
    }

    public function test_a_failed_category_save_leaves_no_half_saved_category(): void
    {
        $this->breakPivotInsert('category_option_group');
        $this->withoutExceptionHandling();

        try {
            $this->post('/admin/categories', ['name' => 'Snack', 'option_groups' => [$this->group->id]]);
            $this->fail('Seharusnya gagal.');
        } catch (RuntimeException) {
            // diharapkan
        }

        $this->assertDatabaseMissing('categories', ['name' => 'Snack']);
    }

    public function test_a_failed_category_update_keeps_the_old_name(): void
    {
        $this->breakPivotInsert('category_option_group');
        $this->withoutExceptionHandling();

        try {
            $this->put("/admin/categories/{$this->category->id}", ['name' => 'Nama Baru', 'option_groups' => [$this->group->id]]);
            $this->fail('Seharusnya gagal.');
        } catch (RuntimeException) {
            // diharapkan
        }

        $this->assertSame('Mie', $this->category->fresh()->name);
    }
}
