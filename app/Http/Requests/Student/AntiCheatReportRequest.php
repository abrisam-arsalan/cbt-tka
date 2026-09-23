<?php

namespace App\Http\Requests\Student;

use App\Enums\AntiCheatEventType;
use Illuminate\Foundation\Http\FormRequest;

class AntiCheatReportRequest extends FormRequest
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
        $allowed = implode(',', AntiCheatEventType::values());

        return [
            'events' => ['required', 'array', 'min:1', 'max:50'],
            'events.*.type' => ['required', 'string', "in:{$allowed}"],
            'events.*.message' => ['nullable', 'string', 'max:255'],
            'events.*.meta' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'events.*.type.in' => 'Jenis kejadian tidak dikenal.',
        ];
    }
}
