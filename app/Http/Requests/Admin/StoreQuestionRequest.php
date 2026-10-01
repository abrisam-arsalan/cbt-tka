<?php

namespace App\Http\Requests\Admin;

use App\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
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
        $types = implode(',', QuestionType::values());

        return [
            'type' => [$isUpdate ? 'sometimes' : 'required', Rule::in(QuestionType::values())],
            'stimulus' => ['nullable', 'string', 'max:10000'],
            'question_text' => ['required', 'string', 'max:10000'],
            'media_url' => ['nullable', 'string', 'max:2048'],
            'order' => [$isUpdate ? 'sometimes' : 'nullable', 'integer', 'min:0'],
            'is_active' => [$isUpdate ? 'sometimes' : 'nullable', 'boolean'],

            'options' => ['nullable', 'array', 'max:10'],
            // id baris lama — dipakai QuestionContentWriter agar ID opsi tidak
            // berganti saat soal diedit (jawaban siswa mereferensikannya).
            'options.*.id' => ['nullable', 'integer'],
            'options.*.label' => ['nullable', 'string', 'max:8'],
            'options.*.option_text' => ['required_with:options', 'string', 'max:2000'],
            'options.*.media_url' => ['nullable', 'string', 'max:2048'],
            'options.*.is_correct' => ['nullable', 'boolean'],

            'matching_pairs' => ['nullable', 'array', 'max:20'],
            'matching_pairs.*.id' => ['nullable', 'integer'],
            'matching_pairs.*.left_text' => ['required_with:matching_pairs', 'string', 'max:1000'],
            'matching_pairs.*.right_text' => ['required_with:matching_pairs', 'string', 'max:1000'],
            'matching_pairs.*.left_media_url' => ['nullable', 'string', 'max:2048'],
            'matching_pairs.*.right_media_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');

            if ($type === null) {
                return;
            }

            $type = QuestionType::from($type);
            $options = $this->input('options', []);
            $pairs = $this->input('matching_pairs', []);

            if ($type->usesOptions()) {
                $correctCount = count(array_filter($options, fn ($o) => (bool) ($o['is_correct'] ?? false)));

                if ($type === QuestionType::Pg && $correctCount !== 1) {
                    $validator->errors()->add('options', 'Pilihan ganda harus memiliki tepat satu jawaban benar.');
                }

                if ($type === QuestionType::Pgk && $correctCount < 1) {
                    $validator->errors()->add('options', 'Pilihan ganda kompleks harus memiliki minimal satu jawaban benar.');
                }

                if ($type === QuestionType::Boolean && $correctCount !== 1) {
                    $validator->errors()->add('options', 'Soal benar/salah harus memiliki tepat satu opsi benar.');
                }
            }

            if ($type === QuestionType::Matching && count($pairs) < 2) {
                $validator->errors()->add('matching_pairs', 'Soal menjodohkan minimal memiliki dua pasangan.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'question_text.required' => 'Teks soal wajib diisi.',
            'options.*.option_text.required_with' => 'Teks opsi tidak boleh kosong.',
            'matching_pairs.*.left_text.required_with' => 'Teks kiri wajib diisi.',
            'matching_pairs.*.right_text.required_with' => 'Teks kanan wajib diisi.',
        ];
    }
}
