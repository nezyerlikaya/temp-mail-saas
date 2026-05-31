<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LanguageDirection;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\LocalizationAudit;
use App\Models\Translation;
use App\Services\System\LocalizationProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocalizationController extends Controller
{
    public function index(LocalizationProgressService $progress): View
    {
        return view('admin.localization.index', [
            'languages' => Language::query()->orderBy('sort_order')->get(),
            'progress' => $progress->all(),
            'audits' => LocalizationAudit::query()->latest()->limit(10)->get(),
        ]);
    }

    public function languages(): View
    {
        return view('admin.localization.languages', [
            'languages' => Language::query()->orderBy('sort_order')->paginate(15),
        ]);
    }

    public function storeLanguage(Request $request): RedirectResponse
    {
        $data = $this->languageData($request);

        $language = Language::query()->create($data);
        $this->audit($request, $language, 'language.created', null, null, json_encode($language->only(['code', 'name'])));

        return back()->with('status', 'Language created.');
    }

    public function editLanguage(Language $language): View
    {
        return view('admin.localization.language-edit', [
            'language' => $language,
        ]);
    }

    public function updateLanguage(Request $request, Language $language): RedirectResponse
    {
        $data = $this->languageData($request, $language);
        $old = $language->toJson();

        $language->update($data);
        $this->audit($request, $language, 'language.updated', null, $old, $language->fresh()->toJson());

        return redirect()->route('admin.localization.languages')->with('status', 'Language updated.');
    }

    public function activate(Request $request, Language $language): RedirectResponse
    {
        $language->update(['is_active' => true]);
        $this->audit($request, $language, 'language.activated');

        return back()->with('status', 'Language activated.');
    }

    public function deactivate(Request $request, Language $language): RedirectResponse
    {
        if ($language->isDefault()) {
            return back()->withErrors(['language' => 'Default language cannot be disabled.']);
        }

        if (Language::query()->where('is_active', true)->whereKeyNot($language->id)->count() === 0) {
            return back()->withErrors(['language' => 'At least one active language is required.']);
        }

        $language->update(['is_active' => false]);
        $this->audit($request, $language, 'language.deactivated');

        return back()->with('status', 'Language deactivated.');
    }

    public function makeDefault(Request $request, Language $language): RedirectResponse
    {
        $language->update([
            'is_active' => true,
            'is_default' => true,
        ]);
        $this->audit($request, $language, 'language.defaulted');

        return back()->with('status', 'Default language updated.');
    }

    public function destroyLanguage(Request $request, Language $language): RedirectResponse
    {
        if ($language->isDefault()) {
            return back()->withErrors(['language' => 'Default language cannot be deleted.']);
        }

        $this->audit($request, $language, 'language.deleted', null, $language->code, null);
        $language->delete();

        return back()->with('status', 'Language deleted.');
    }

    public function translations(Request $request, LocalizationProgressService $progress): View
    {
        $search = trim((string) $request->query('search', ''));
        $languageId = $request->query('language_id');
        $group = trim((string) $request->query('group', ''));

        $query = Translation::query()->with('language')
            ->when($languageId, fn ($query) => $query->where('language_id', $languageId))
            ->when($group !== '', fn ($query) => $query->where('group', $group))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('key', 'like', "%{$search}%")
                        ->orWhere('group', 'like', "%{$search}%")
                        ->orWhere('value', 'like', "%{$search}%");
                });
            });

        return view('admin.localization.translations', [
            'translations' => $query->orderBy('group')->orderBy('key')->paginate(20)->withQueryString(),
            'languages' => Language::query()->orderBy('sort_order')->get(),
            'groups' => Translation::query()->distinct()->orderBy('group')->pluck('group'),
            'filters' => compact('search', 'languageId', 'group'),
            'progress' => $progress->all(),
        ]);
    }

    public function updateTranslations(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'translations' => ['required', 'array'],
            'translations.*' => ['nullable', 'string'],
        ]);

        foreach ($data['translations'] as $id => $value) {
            $translation = Translation::query()->find($id);

            if ($translation === null) {
                continue;
            }

            $old = $translation->value;
            $translation->update([
                'value' => $value,
                'is_custom' => true,
            ]);
            $this->audit($request, $translation->language, 'translation.updated', $translation->group.'.'.$translation->key, $old, $value);
        }

        return back()->with('status', 'Translations updated.');
    }

    public function importForm(): View
    {
        return view('admin.localization.import', [
            'languages' => Language::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'language_id' => ['required', 'exists:languages,id'],
            'json' => ['required', 'string'],
        ]);

        $decoded = json_decode($data['json'], true);

        if (! is_array($decoded)) {
            return back()->withErrors(['json' => 'The import payload must be valid JSON.']);
        }

        $language = Language::query()->findOrFail($data['language_id']);
        $updated = 0;

        foreach ($decoded as $group => $translations) {
            if (! is_array($translations)) {
                continue;
            }

            foreach ($translations as $key => $value) {
                if (! is_scalar($value) && $value !== null) {
                    continue;
                }

                $translation = Translation::query()->updateOrCreate(
                    [
                        'language_id' => $language->id,
                        'group' => (string) $group,
                        'key' => (string) $key,
                    ],
                    [
                        'value' => $value,
                        'is_custom' => true,
                    ],
                );
                $this->audit($request, $language, 'translation.imported', $translation->group.'.'.$translation->key, null, (string) $value);
                $updated++;
            }
        }

        return back()->with('status', "{$updated} translations imported.");
    }

    public function export(Request $request): Response|View
    {
        $language = Language::query()->where('id', $request->query('language_id'))->first();

        if ($language === null) {
            return view('admin.localization.export', [
                'languages' => Language::query()->orderBy('sort_order')->get(),
            ]);
        }

        $payload = [];

        foreach ($language->translations()->orderBy('group')->orderBy('key')->get() as $translation) {
            $payload[$translation->group][$translation->key] = $translation->value;
        }

        return response(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$language->code.'-translations.json"',
        ]);
    }

    private function languageData(Request $request, ?Language $language = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:16',
                'regex:/^[a-z]{2}(?:[-_][A-Za-z]{2})?$/',
                Rule::unique('languages', 'code')->ignore($language),
            ],
            'name' => ['required', 'string', 'max:255'],
            'native_name' => ['required', 'string', 'max:255'],
            'direction' => ['required', Rule::in(array_column(LanguageDirection::cases(), 'value'))],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function audit(Request $request, ?Language $language, string $action, ?string $key = null, ?string $old = null, ?string $new = null): void
    {
        LocalizationAudit::query()->create([
            'language_id' => $language?->id,
            'staff_user_id' => auth('staff')->id(),
            'action' => $action,
            'key' => $key,
            'old_value' => $old,
            'new_value' => $new,
            'created_at' => now(),
        ]);
    }
}
