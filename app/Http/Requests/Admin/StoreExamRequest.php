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
            // Target ujian: class_id (rombel) ATAU grade (jenjang 7/8/9), tidak keduanya.
            'class_id' => [$isUpdate ? 'sometimes' : 'nullable', 'integer', 'exists:classes,id', 'exclude_with:grade'],
            'grade' => [$isUpdate ? 'sometimes' : 'nullable', 'string', Rule::in(['7', '8', '9']), 'exclude_with:class_id'],
            // Hanya dipakai saat membuat ujian: bank soal yang isinya disalin.
            'batch_ids' => ['sometimes', 'nullable', 'array'],
            'batch_ids.*' => ['integer', 'exists:question_batches,id'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            // Jumlah soal yang keluar (mis. 30 dari bank 60). Kosong = semua.
            'question_count' => [$isUpdate ? 'sometimes' : 'nullable', 'integer', 'min:1', 'max:600'],
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
