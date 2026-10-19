<?php

namespace Tests\Feature\Admin;

use App\Models\OptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Buat level cepat" di halaman Tambah Grup Opsi: Level 0 sampai 8 dibuat sekaligus, lalu dipasang ke kategori atau menu. */
class OptionGroupLevelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'group_name' => 'Level Pedas',
            'prefix' => 'Level',
            'from' => 0,
            'to' => 8,
            'required' => '1',
        ], $overrides);
    }

    public function test_one_form_creates_the_group_with_all_levels_zero_to_eight(): void
    {
        $this->post('/admin/option-groups/level', $this->payload())->assertRedirect();

        $group = OptionGroup::firstOrFail();

        $this->assertSame('Level Pedas', $group->name);
        $this->assertSame(
            ['Level 0', 'Level 1', 'Level 2', 'Level 3', 'Level 4', 'Level 5', 'Level 6', 'Level 7', 'Level 8'],
            $group->options->pluck('name')->all(),
        );
        $this->assertTrue($group->options->every(fn ($o) => $o->extra_price === 0 && $o->is_available));
    }

    public function test_it_sends_the_admin_to_the_group_page_to_edit_further(): void
    {
        $response = $this->post('/admin/option-groups/level', $this->payload());

        $group = OptionGroup::firstOrFail();
        $response->assertRedirect("/admin/option-groups/{$group->id}/edit");
        $this->get("/admin/option-groups/{$group->id}/edit")->assertOk()->assertSee('Level 8')->assertSee('siap');
    }

    public function test_the_group_is_required_choose_one_by_default_and_can_be_optional(): void
    {
        $this->post('/admin/option-groups/level', $this->payload());
        $this->post('/admin/option-groups/level', $this->payload(['group_name' => 'Level Manis', 'required' => '0']));

        $required = OptionGroup::where('name', 'Level Pedas')->firstOrFail();
        $optional = OptionGroup::where('name', 'Level Manis')->firstOrFail();

        $this->assertSame([1, 1], [$required->min_select, $required->max_select]);
        $this->assertSame([0, 1], [$optional->min_select, $optional->max_select]);
    }

    public function test_surcharge_applies_from_the_chosen_level_upwards(): void
    {
        $this->post('/admin/option-groups/level', $this->payload(['to' => 5, 'surcharge_from' => 3, 'surcharge' => 1000]));

        $prices = OptionGroup::firstOrFail()->options->pluck('extra_price', 'name')->all();

        $this->assertSame(0, $prices['Level 2']);
        $this->assertSame(1000, $prices['Level 3']);
        $this->assertSame(1000, $prices['Level 5']);
    }

    public function test_a_custom_range_and_prefix_work(): void
    {
        $this->post('/admin/option-groups/level', $this->payload(['group_name' => 'Porsi', 'prefix' => 'Porsi', 'from' => 1, 'to' => 3]));

        $this->assertSame(['Porsi 1', 'Porsi 2', 'Porsi 3'], OptionGroup::firstOrFail()->options->pluck('name')->all());
    }

    public function test_running_it_again_reuses_the_group_adds_only_missing_levels_and_keeps_prices(): void
    {
        $this->post('/admin/option-groups/level', $this->payload(['to' => 4, 'surcharge_from' => 3, 'surcharge' => 1000]));
        $group = OptionGroup::firstOrFail();
        $group->options()->where('name', 'Level 3')->update(['extra_price' => 2500]); // admin mengubah harga manual

        $this->post('/admin/option-groups/level', $this->payload(['to' => 6, 'surcharge_from' => 3, 'surcharge' => 1000]));

        $this->assertSame(1, OptionGroup::where('name', 'Level Pedas')->count());
        $options = $group->fresh()->options->keyBy('name');
        $this->assertCount(7, $options);
        $this->assertSame(2500, $options['Level 3']->extra_price, 'harga yang sudah diatur tidak ditimpa');
        $this->assertSame(1000, $options['Level 5']->extra_price, 'level baru memakai harga dari form');
    }

    public function test_an_existing_groups_rules_are_not_changed(): void
    {
        $this->post('/admin/option-groups/level', $this->payload(['required' => '1']));
        $this->post('/admin/option-groups/level', $this->payload(['required' => '0', 'to' => 9]));

        $group = OptionGroup::firstOrFail();
        $this->assertSame(1, $group->min_select, 'grup mungkin dipakai menu lain, aturannya tidak diubah');
        $this->assertCount(10, $group->options);
    }

    public function test_a_trashed_group_with_the_same_name_is_not_reused(): void
    {
        $this->post('/admin/option-groups/level', $this->payload(['to' => 2]));
        OptionGroup::firstOrFail()->delete();

        $this->post('/admin/option-groups/level', $this->payload(['to' => 2]));

        $this->assertSame(1, OptionGroup::count());
        $this->assertSame(2, OptionGroup::withTrashed()->count(), 'yang di Sampah tidak dipakai lagi, dibuat grup baru');
    }

    public function test_the_range_is_validated(): void
    {
        $this->post('/admin/option-groups/level', $this->payload(['from' => 5, 'to' => 2]))->assertSessionHasErrors('to');
        $this->post('/admin/option-groups/level', $this->payload(['from' => '', 'to' => '']))->assertSessionHasErrors(['from', 'to']);
        $this->post('/admin/option-groups/level', $this->payload(['group_name' => '']))->assertSessionHasErrors('group_name');
        $this->post('/admin/option-groups/level', $this->payload(['prefix' => '']))->assertSessionHasErrors('prefix');

        $this->assertDatabaseCount('option_groups', 0);
    }

    public function test_too_many_levels_at_once_are_rejected(): void
    {
        $this->post('/admin/option-groups/level', $this->payload(['from' => 0, 'to' => 30]))->assertSessionHasErrors('to');

        $this->assertDatabaseCount('option_groups', 0);
    }

    public function test_the_create_page_offers_both_the_level_builder_and_a_plain_group(): void
    {
        $this->get('/admin/option-groups/create')->assertOk()
            ->assertSee('Buat level cepat')->assertSee('Level Pedas')->assertSee('Buat grup biasa');
    }

    public function test_the_menu_form_no_longer_creates_levels_and_only_picks_existing_groups(): void
    {
        $this->get('/admin/menus/create')->assertOk()
            ->assertDontSee('Tambahkan level')->assertSee('Grup opsi yang dipasang langsung');
    }

    public function test_only_admins_can_use_it(): void
    {
        auth()->logout();
        $this->actingAs(User::factory()->create());

        $this->post('/admin/option-groups/level', $this->payload())->assertForbidden();
    }
}
