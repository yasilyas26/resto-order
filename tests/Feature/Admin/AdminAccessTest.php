<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Menyembunyikan link bukan pengamanan; middleware-lah yang mengamankan.
 * Tes ini memastikan semua halaman admin tertutup untuk tamu, customer, dan staff.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function adminUrls(): array
    {
        return [
            'dashboard' => ['/admin'],
            'kategori' => ['/admin/categories'],
            'tambah kategori' => ['/admin/categories/create'],
            'menu' => ['/admin/menus'],
            'tambah menu' => ['/admin/menus/create'],
            'grup opsi' => ['/admin/option-groups'],
            'tambah grup opsi' => ['/admin/option-groups/create'],
            'meja' => ['/admin/tables'],
            'tambah meja' => ['/admin/tables/create'],
            'sampah' => ['/admin/trash'],
        ];
    }

    #[DataProvider('adminUrls')]
    public function test_guest_is_redirected_to_login(string $url): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    #[DataProvider('adminUrls')]
    public function test_customer_gets_403(string $url): void
    {
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    #[DataProvider('adminUrls')]
    public function test_staff_gets_403(string $url): void
    {
        $this->actingAs(User::factory()->staff()->create())->get($url)->assertForbidden();
    }

    #[DataProvider('adminUrls')]
    public function test_admin_can_open_the_page(string $url): void
    {
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
    }

    public function test_write_actions_are_also_closed_to_customers(): void
    {
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Makanan']);
        $table = Table::create(['number' => 'A01', 'is_active' => true]);

        $this->actingAs($customer)->post('/admin/categories', ['name' => 'X'])->assertForbidden();
        $this->actingAs($customer)->delete("/admin/categories/{$category->id}")->assertForbidden();
        $this->actingAs($customer)->post("/admin/tables/{$table->id}/regenerate")->assertForbidden();
        $this->actingAs($customer)->delete("/admin/trash/categories/{$category->id}")->assertForbidden();

        $this->assertDatabaseCount('categories', 1);
    }

    public function test_admin_can_also_open_staff_area_but_customer_cannot(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/staff')->assertRedirect('/staff/orders');
        $this->actingAs(User::factory()->staff()->create())->get('/staff')->assertRedirect('/staff/orders');
        $this->actingAs(User::factory()->create())->get('/staff')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->get('/staff/orders')->assertOk();
        $this->actingAs(User::factory()->staff()->create())->get('/staff/orders')->assertOk();
        $this->actingAs(User::factory()->create())->get('/staff/orders')->assertForbidden();
    }
}
