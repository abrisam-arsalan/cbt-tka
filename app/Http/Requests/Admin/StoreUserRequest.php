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
            // Username login siswa = NISN. Boleh dikosongkan bila NISN diisi
            // (UserController akan memakainya sebagai username).
            'username' => [
                $isUpdate ? 'sometimes' : 'required_without:nisn',
                'string', 'max:64',
                Rule::unique('users', 'username')->ignore($isUpdate ? $userId : null),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($isUpdate ? $userId : null),
            ],
            // Password siswa adalah PIN numerik. Kosong saat dibuat => digenerate
            // otomatis 6 digit oleh PinService ( tercetak di kartu ujian).
            'password' => ['nullable', 'string', 'regex:/^\d{4,8}$/', 'confirmed', 'different:username'],
            'role' => ['required', Rule::in(UserRole::values())],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'nisn' => [
                $isUpdate ? 'nullable' : 'required_without:username',
                'string', 'max:32', 'regex:/^\d+$/',
                Rule::unique('users', 'nisn')->ignore($isUpdate ? $userId : null),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required_without' => 'Isi NISN (username login memakai NISN) atau username secara manual.',
            'username.unique' => 'Username ini sudah dipakai akun lain.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
            'nisn.required_without' => 'Isi username atau NISN.',
            'nisn.regex' => 'NISN harus berupa angka.',
            'nisn.unique' => 'NISN ini sudah terdaftar di akun lain.',
            'password.regex' => 'PIN siswa harus berupa 4-8 digit angka.',
            'password.confirmed' => 'Konfirmasi PIN tidak cocok.',
            'password.different' => 'PIN tidak boleh sama dengan username.',
        ];
    }
}
