<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class JoinExamRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Token ujian wajib diisi.',
            'token.max' => 'Format token tidak sesuai.',
        ];
    }
}
