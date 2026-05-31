<?php

namespace Tests\Feature;

use App\Enums\LanguageDirection;
use App\Models\Language;
use App\Models\Translation;
use App\Models\User;
use App\Services\System\LocaleService;
use App\Services\System\TranslationService;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocalizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_and_translation_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('languages'));
        $this->assertTrue(Schema::hasTable('translations'));

        $this->assertTrue(Schema::hasColumns('languages', [
            'code',
            'name',
            'native_name',
            'direction',
            'is_active',
            'is_default',
            'sort_order',
        ]));

        $this->assertTrue(Schema::hasColumns('translations', [
            'language_id',
            'group',
            'key',
            'value',
            'is_custom',
        ]));
    }

    public function test_default_language_is_seeded_and_only_one_default_is_supported(): void
    {
        $this->seed(LanguageSeeder::class);

        $this->assertSame(1, Language::query()->where('is_default', true)->count());
        $this->assertTrue(Language::query()->where('code', 'en')->firstOrFail()->isDefault());

        Language::query()->create([
            'code' => 'ar',
            'name' => 'Arabic',
            'native_name' => 'Arabic',
            'direction' => LanguageDirection::Rtl,
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 30,
        ]);

        $this->assertSame(1, Language::query()->where('is_default', true)->count());
        $this->assertTrue(Language::query()->where('code', 'ar')->firstOrFail()->isDefault());
        $this->assertFalse(Language::query()->where('code', 'en')->firstOrFail()->isDefault());
    }

    public function test_language_model_helpers_work(): void
    {
        $language = Language::query()->create([
            'code' => 'fa',
            'name' => 'Persian',
            'native_name' => 'Persian',
            'direction' => LanguageDirection::Rtl,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 40,
        ]);

        $this->assertTrue($language->isActive());
        $this->assertFalse($language->isDefault());
        $this->assertTrue($language->isRtl());
        $this->assertFalse($language->isLtr());
    }

    public function test_locale_service_rejects_invalid_locale_and_falls_back_safely(): void
    {
        $this->seed(LanguageSeeder::class);

        $service = app(LocaleService::class);

        $this->assertTrue($service->isValidLocale('en'));
        $this->assertFalse($service->isValidLocale('xx'));
        $this->assertFalse($service->isValidLocale('../en'));
        $this->assertSame('en', $service->setApplicationLocale('xx'));
    }

    public function test_locale_service_prefers_authenticated_user_locale_when_valid(): void
    {
        $this->seed(LanguageSeeder::class);
        $user = User::factory()->create(['locale' => 'tr']);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get('/')
            ->assertOk();

        $this->assertSame('tr', app()->getLocale());
    }

    public function test_locale_switch_route_stores_valid_locale_in_session(): void
    {
        $this->seed(LanguageSeeder::class);

        $this->from('/')->post('/locale', [
            'locale' => 'tr',
        ])->assertRedirect('/');

        $this->assertSame('tr', session('locale'));
    }

    public function test_locale_switch_route_rejects_invalid_locale_safely(): void
    {
        $this->seed(LanguageSeeder::class);

        $this->from('/')->post('/locale', [
            'locale' => 'xx',
        ])->assertRedirect('/')
            ->assertSessionHasErrors('locale');

        $this->assertNull(session('locale'));
    }

    public function test_translation_service_returns_default_text_when_missing(): void
    {
        $this->seed(LanguageSeeder::class);

        $this->assertSame(
            'Default copy',
            app(TranslationService::class)->get('missing', 'key', 'en', 'Default copy'),
        );
    }

    public function test_translation_service_reads_database_value_and_relationships_work(): void
    {
        $this->seed(LanguageSeeder::class);
        $language = Language::query()->where('code', 'tr')->firstOrFail();

        Translation::query()->create([
            'language_id' => $language->id,
            'group' => 'auth',
            'key' => 'login',
            'value' => 'Giris',
            'is_custom' => true,
        ]);

        $translation = Translation::query()->where('key', 'login')->firstOrFail();

        $this->assertTrue($language->translations()->where('key', 'login')->exists());
        $this->assertTrue($translation->language->is($language));
        $this->assertSame('Giris', app(TranslationService::class)->get('auth', 'login', 'tr', 'Login'));
        $this->assertTrue(app(TranslationService::class)->exists('auth', 'login', 'tr'));
    }

    public function test_existing_public_auth_and_admin_routes_still_work(): void
    {
        $this->seed(LanguageSeeder::class);

        $this->get('/')->assertOk();
        $this->getJson('/health')->assertOk();
        $this->get('/status')->assertOk();
        $this->get('/up')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/admin')->assertForbidden();
    }
}
