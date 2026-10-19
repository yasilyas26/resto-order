<?php

namespace Tests\Feature;

use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pesan validasi memakai nama field berbahasa Indonesia ("Nama wajib diisi.", bukan "Name wajib diisi."). */
class IndonesianMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_form_errors_use_indonesian_field_names(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post('/admin/categories', ['name' => ''])
            ->assertSessionHasErrors(['name' => 'Nama wajib diisi.']);

        $this->post('/admin/menus', ['name' => 'Mie'])
            ->assertSessionHasErrors(['price' => 'Harga wajib diisi.', 'category_id' => 'Kategori wajib diisi.']);
    }

    public function test_the_level_builder_errors_use_indonesian_field_names(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post('/admin/option-groups/level', ['group_name' => 'Level', 'prefix' => 'Lv', 'from' => 'abc', 'to' => 5])
            ->assertSessionHasErrors(['from' => 'Level awal harus berupa bilangan bulat.']);
    }

    public function test_profile_errors_use_indonesian_field_names(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', ['name' => '', 'email' => 'bukan-email'])
            ->assertSessionHasErrors(['name' => 'Nama wajib diisi.', 'email' => 'Email harus berupa alamat email yang valid.']);

        $this->put('/password', ['current_password' => 'password', 'password' => 'Baru12345xyz', 'password_confirmation' => 'beda'])
            ->assertSessionHasErrorsIn('updatePassword', ['password' => 'Konfirmasi kata sandi tidak cocok.']);
    }

    public function test_checkout_errors_use_indonesian_field_names(): void
    {
        $this->postJson('/checkout', ['order_type' => 'delivery', 'items' => []])
            ->assertStatus(409); // tanpa meja: ditolak lebih dulu, bukan karena validasi

        $table = Table::create(['number' => 'A07']);

        $this->withSession(['table_id' => $table->id])
            ->postJson('/checkout', ['order_type' => 'delivery', 'items' => []])
            ->assertJsonValidationErrors(['order_type', 'items'])
            ->assertJsonPath('errors.items.0', 'Keranjang masih kosong.');
    }

    public function test_registration_errors_are_indonesian(): void
    {
        $this->post('/register', ['name' => '', 'email' => '', 'password' => 'abc', 'password_confirmation' => 'abc'])
            ->assertSessionHasErrors(['name' => 'Nama wajib diisi.', 'email' => 'Email wajib diisi.']);
    }
}
