<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class HeartbeatRequest extends FormRequest
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
        return [
            'status' => ['nullable', 'string', 'in:online,idle,offline'],
            'last_client_seq' => ['nullable', 'integer', 'min:0'],
            'outbox_pending' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
