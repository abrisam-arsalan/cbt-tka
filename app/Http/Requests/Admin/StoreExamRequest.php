<?php

namespace App\Http\Requests\Admin;

use App\Enums\AntiCheatAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        $actions = implode(',', AntiCheatAction::values());

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'anti_cheat_enabled' => [$isUpdate ? 'sometimes' : 'nullable', 'boolean'],
            'anti_cheat_max_warnings' => [$isUpdate ? 'sometimes' : 'nullable', 'integer', 'min:0', 'max:10'],
            'anti_cheat_action' => [$isUpdate ? 'sometimes' : 'nullable', Rule::in(AntiCheatAction::values())],
            'shuffle_questions' => [$isUpdate ? 'sometimes' : 'nullable', 'boolean'],
            'shuffle_options' => [$isUpdate ? 'sometimes' : 'nullable', 'boolean'],
            'offline_grace_minutes' => [$isUpdate ? 'sometimes' : 'nullable', 'integer', 'min:0', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_at.after' => 'Waktu selesai harus setelah waktu mulai.',
            'duration_minutes.max' => 'Durasi ujian maksimum 600 menit (10 jam).',
            'anti_cheat_action.in' => 'Tindakan anti-cheat tidak valid.',
        ];
    }
}
