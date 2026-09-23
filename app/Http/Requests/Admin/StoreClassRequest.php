<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassRequest extends FormRequest
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
        $classId = (int) $this->route('class')?->id;
        $year = $this->input('academic_year');

        return [
            'name' => [
                'required', 'string', 'max:64',
                $isUpdate
                    ? Rule::unique('classes')->where('academic_year', $year)->ignore($classId)
                    : Rule::unique('classes')->where('academic_year', $year),
            ],
            'grade' => ['nullable', 'string', 'max:16'],
            'academic_year' => ['nullable', 'string', 'max:16'],
            'description' => ['nullable', 'string'],
            'is_active' => [$isUpdate ? 'sometimes' : 'nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Nama kelas di tahun ajaran ini sudah ada.',
        ];
    }
}
