<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly AuditLogService $audit,
    ) {}

    public function index(): Response
    {
        $all = \App\Models\Setting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get();

        $grouped = $all->groupBy('group')->map(fn ($items) => $items->map(fn ($s) => [
            'key' => $s->key,
            'value' => $s->typedValue(),
            'raw_value' => $s->value,
            'type' => $s->type,
            'group' => $s->group,
            'label' => $s->label ?? $s->key,
            'description' => $s->description,
            'is_public' => (bool) $s->is_public,
        ])->values());

        return Inertia::render('Admin/Settings/Index', [
            'title' => 'Pengaturan Sistem',
            'groups' => $grouped,
            'presenceDriver' => app(\App\Services\PresenceService::class)->driverName(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:120'],
            'settings.*.value' => ['nullable'],
            'settings.*.type' => ['required', Rule::in(['string', 'int', 'bool', 'json'])],
        ]);

        $changed = [];

        foreach ($validated['settings'] as $setting) {
            $existing = \App\Models\Setting::query()->where('key', $setting['key'])->first();

            if ($existing === null) {
                continue;
            }

            $before = $existing->typedValue();
            $after = match ($setting['type']) {
                'bool' => filter_var($setting['value'], FILTER_VALIDATE_BOOLEAN),
                'int' => (int) $setting['value'],
                default => (string) $setting['value'],
            };

            if ((string) $before !== (string) $after) {
                $changed[$setting['key']] = ['from' => $before, 'to' => $after];
            }

            $this->settings->set($setting['key'], $after, $setting['type']);
        }

        if ($changed !== []) {
            $this->audit->log(
                action: 'settings.updated',
                description: count($changed).' pengaturan diubah.',
                meta: ['changes' => $changed],
            );
        }

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
