<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
        $userId = (int) $this->route('user')?->id;

        return [
            'username' => [
                'required', 'string', 'max:64',
                Rule::unique('users', 'username')->ignore($isUpdate ? $userId : null),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($isUpdate ? $userId : null),
            ],
            'password' => $isUpdate
                ? ['nullable', 'string', 'min:6', 'confirmed']
                : ['required', 'string', 'min:6', 'confirmed'],
            'role' => ['required', Rule::in(UserRole::values())],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'nisn' => [
                'nullable', 'string', 'max:32',
                Rule::unique('users', 'nisn')->ignore($isUpdate ? $userId : null),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Username ini sudah dipakai akun lain.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
            'nisn.unique' => 'NISN ini sudah terdaftar di akun lain.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}
