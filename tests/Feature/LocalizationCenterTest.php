<?php

namespace Tests\Feature;

use App\Enums\LanguageDirection;
use App\Enums\StaffStatus;
use App\Models\Language;
use App\Models\LocalizationAudit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffUser;
use App\Models\Translation;
use App\Services\System\LocalizationProgressService;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\TranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocalizationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_localization_audit_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('localization_audits'));
        $this->assertTrue(Schema::hasColumns('localization_audits', [
            'language_id',
            'staff_user_id',
            'action',
            'key',
            'old_value',
            'new_value',
            'created_at',
        ]));
    }

    public function test_permissions_are_enforced(): void
    {
        $this->get('/admin/localization')->assertForbidden();

        $this->actingAs($this->staffWithPermissions([]), 'staff')
            ->get('/admin/localization')
            ->assertForbidden();

        $this->actingAs($this->staffWithPermissions(['localization.view']), 'staff')
            ->get('/admin/localization')
            ->assertOk()
            ->assertSee('Localization Center');

        $this->post('/admin/localization/languages', [])
            ->assertForbidden();
    }

    public function test_language_crud_works(): void
    {
        $this->seed(LanguageSeeder::class);

        $this->actingAs($this->staffWithPermissions([
            'localization.view',
            'localization.manage',
        ]), 'staff');

        $this->post('/admin/localization/languages', [
            'code' => 'fr',
            'name' => 'French',
            'native_name' => 'Francais',
            'direction' => 'ltr',
            'is_active' => '1',
            'sort_order' => 30,
        ])->assertRedirect();

        $language = Language::query()->where('code', 'fr')->firstOrFail();

        $this->assertTrue($language->isActive());
        $this->assertDatabaseHas('localization_audits', ['action' => 'language.created']);

        $this->put("/admin/localization/languages/{$language->id}", [
            'code' => 'fr',
            'name' => 'French Updated',
            'native_name' => 'Francais',
            'direction' => 'ltr',
            'is_active' => '1',
            'sort_order' => 35,
        ])->assertRedirect(route('admin.localization.languages'));

        $this->assertDatabaseHas('languages', [
            'code' => 'fr',
            'name' => 'French Updated',
            'sort_order' => 35,
        ]);
    }

    public function test_default_language_rules_work(): void
    {
        $this->seed(LanguageSeeder::class);

        $this->actingAs($this->staffWithPermissions([
            'localization.view',
            'localization.manage',
        ]), 'staff');

        $arabic = Language::query()->create([
            'code' => 'ar',
            'name' => 'Arabic',
            'native_name' => 'Arabic',
            'direction' => LanguageDirection::Rtl,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 30,
        ]);

        $this->patch("/admin/localization/languages/{$arabic->id}/default")
            ->assertRedirect();

        $this->assertSame(1, Language::query()->where('is_default', true)->count());
        $this->assertTrue($arabic->fresh()->isDefault());

        $this->delete("/admin/localization/languages/{$arabic->id}")
            ->assertSessionHasErrors('language');

        $this->assertDatabaseHas('languages', ['code' => 'ar']);
    }

    public function test_active_language_rules_work(): void
    {
        $this->seed(LanguageSeeder::class);

        $this->actingAs($this->staffWithPermissions([
            'localization.view',
            'localization.manage',
        ]), 'staff');

        Language::query()->where('code', 'tr')->update(['is_active' => false]);
        $english = Language::query()->where('code', 'en')->firstOrFail();

        $this->patch("/admin/localization/languages/{$english->id}/deactivate")
            ->assertSessionHasErrors('language');

        $this->assertTrue($english->fresh()->isActive());
    }

    public function test_translation_updates_create_audit_records(): void
    {
        $this->seed(LanguageSeeder::class);
        $this->seed(TranslationSeeder::class);
        $translation = Translation::query()->where('key', 'status')->firstOrFail();

        $this->actingAs($this->staffWithPermissions([
            'localization.view',
            'localization.manage',
        ]), 'staff');

        $this->put('/admin/localization/translations', [
            'translations' => [
                $translation->id => 'Updated status',
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'value' => 'Updated status',
            'is_custom' => true,
        ]);
        $this->assertDatabaseHas('localization_audits', [
            'action' => 'translation.updated',
            'key' => $translation->group.'.'.$translation->key,
        ]);
    }

    public function test_import_works(): void
    {
        $this->seed(LanguageSeeder::class);
        $language = Language::query()->where('code', 'tr')->firstOrFail();

        $this->actingAs($this->staffWithPermissions(['localization.import']), 'staff')
            ->post('/admin/localization/import', [
                'language_id' => $language->id,
                'json' => '{"marketing":{"headline":"Merhaba"}}',
            ])->assertRedirect();

        $this->assertDatabaseHas('translations', [
            'language_id' => $language->id,
            'group' => 'marketing',
            'key' => 'headline',
            'value' => 'Merhaba',
            'is_custom' => true,
        ]);
        $this->assertDatabaseHas('localization_audits', ['action' => 'translation.imported']);
    }

    public function test_export_works(): void
    {
        $this->seed(LanguageSeeder::class);
        $language = Language::query()->where('code', 'en')->firstOrFail();
        Translation::query()->create([
            'language_id' => $language->id,
            'group' => 'app',
            'key' => 'welcome',
            'value' => 'Welcome',
            'is_custom' => false,
        ]);

        $response = $this->actingAs($this->staffWithPermissions(['localization.export']), 'staff')
            ->get('/admin/localization/export?language_id='.$language->id);

        $response->assertOk()
            ->assertDownload('en-translations.json')
            ->assertSee('"welcome": "Welcome"', false);
    }

    public function test_progress_calculation_works(): void
    {
        $this->seed(LanguageSeeder::class);
        $english = Language::query()->where('code', 'en')->firstOrFail();
        $turkish = Language::query()->where('code', 'tr')->firstOrFail();

        Translation::query()->create([
            'language_id' => $english->id,
            'group' => 'app',
            'key' => 'name',
            'value' => 'Name',
            'is_custom' => false,
        ]);
        Translation::query()->create([
            'language_id' => $english->id,
            'group' => 'app',
            'key' => 'tagline',
            'value' => 'Private inbox',
            'is_custom' => false,
        ]);
        Translation::query()->create([
            'language_id' => $turkish->id,
            'group' => 'app',
            'key' => 'name',
            'value' => 'Ad',
            'is_custom' => true,
        ]);

        $progress = app(LocalizationProgressService::class)->progressFor($turkish);

        $this->assertSame(2, $progress['total']);
        $this->assertSame(1, $progress['completed']);
        $this->assertSame(50, $progress['percent']);
        $this->assertContains('app.tagline', $progress['missing']);
    }

    public function test_locale_switching_uses_active_languages_only(): void
    {
        $this->seed(LanguageSeeder::class);
        Language::query()->where('code', 'tr')->update(['is_active' => false]);

        $this->from('/')->post('/locale', [
            'locale' => 'tr',
        ])->assertRedirect('/')
            ->assertSessionHasErrors('locale');

        $this->assertNull(session('locale'));
    }

    public function test_rtl_support_works(): void
    {
        $this->seed(LanguageSeeder::class);
        Language::query()->create([
            'code' => 'ar',
            'name' => 'Arabic',
            'native_name' => 'Arabic',
            'direction' => LanguageDirection::Rtl,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 30,
        ]);

        $this->withSession(['locale' => 'ar'])
            ->get('/')
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }

    private function staffWithPermissions(array $permissions): StaffUser
    {
        $staff = StaffUser::query()->create([
            'name' => 'Localization Staff',
            'email' => uniqid('staff-', true).'@example.com',
            'password' => Hash::make('password'),
            'status' => StaffStatus::Active,
        ]);

        $role = Role::query()->create([
            'name' => 'Localization Role',
            'slug' => uniqid('localization-role-', false),
            'is_system' => false,
        ]);

        foreach ($permissions as $slug) {
            $permission = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $slug,
                    'group' => str($slug)->before('.')->toString(),
                ],
            );

            $role->permissions()->attach($permission);
        }

        $staff->roles()->attach($role);

        return $staff;
    }
}
