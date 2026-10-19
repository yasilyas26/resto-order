<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_index_lists_categories_in_sort_order(): void
    {
        Category::create(['name' => 'Minuman', 'sort' => 2]);
        Category::create(['name' => 'Makanan', 'sort' => 1]);

        $this->get('/admin/menus?tab=kategori')->assertOk()->assertSeeInOrder(['Makanan', 'Minuman']);
    }

    public function test_admin_can_create_a_category_and_empty_sort_becomes_zero(): void
    {
        $this->post('/admin/categories', ['name' => 'Makanan', 'sort' => ''])
            ->assertRedirect('/admin/menus?tab=kategori');

        $this->assertDatabaseHas('categories', ['name' => 'Makanan', 'sort' => 0]);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        Category::create(['name' => 'Makanan']);

        $this->post('/admin/categories', ['name' => 'Makanan'])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('categories', 1);
    }

    public function test_name_of_a_deleted_category_can_be_used_again(): void
    {
        Category::create(['name' => 'Minuman'])->delete();

        $this->post('/admin/categories', ['name' => 'Minuman'])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Category::where('name', 'Minuman')->count());
    }

    public function test_category_can_keep_its_own_name_when_updated(): void
    {
        $category = Category::create(['name' => 'Minuman', 'sort' => 1]);

        $this->put("/admin/categories/{$category->id}", ['name' => 'Minuman', 'sort' => 5])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(5, $category->fresh()->sort);
    }

    public function test_deleting_a_category_is_a_soft_delete(): void
    {
        $category = Category::create(['name' => 'Minuman']);

        $this->delete("/admin/categories/{$category->id}")->assertRedirect('/admin/menus?tab=kategori');

        $this->assertSoftDeleted($category);
    }

    public function test_category_with_active_menus_cannot_be_deleted(): void
    {
        $category = Category::create(['name' => 'Makanan']);
        Menu::create(['category_id' => $category->id, 'name' => 'Mie', 'price' => 10000]);

        $this->delete("/admin/categories/{$category->id}")->assertSessionHas('error');

        $this->assertNotSoftDeleted($category);
    }

    public function test_deleted_category_cannot_be_edited_by_url(): void
    {
        $category = Category::create(['name' => 'Minuman']);
        $category->delete();

        $this->get("/admin/categories/{$category->id}/edit")->assertNotFound();
    }
}
