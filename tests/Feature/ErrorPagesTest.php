<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Halaman error memakai tampilan merek, berbahasa Indonesia, dan tidak menautkan area admin/karyawan. */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_address_shows_the_branded_404(): void
    {
        $this->get('/halaman-yang-tidak-ada')->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')->assertSee('Ke beranda')
            ->assertDontSee('/admin')->assertDontSee('/staff');
    }

    public function test_wrong_role_sees_the_branded_403(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden()
            ->assertSee('Akses ditolak');
    }

    public function test_every_error_page_renders_without_touching_the_database(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $code) {
            $html = view("errors.{$code}")->render();

            $this->assertStringContainsString("Error {$code}", $html);
            $this->assertStringNotContainsString('/admin', $html);
            $this->assertStringNotContainsString('/staff', $html);
        }
    }

    public function test_expired_pages_offer_a_reload(): void
    {
        $this->assertStringContainsString('Muat ulang', view('errors.419')->render());
    }
}
