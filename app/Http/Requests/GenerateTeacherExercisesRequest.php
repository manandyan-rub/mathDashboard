<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateTeacherExercisesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'max:255'],
            'grade' => ['nullable', 'string', 'max:100'],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'count' => ['required', 'integer', 'min:1', 'max:20'],
            'language' => ['required', Rule::in(['en', 'hy', 'fr', 'ru'])],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'document_id' => ['nullable', 'integer', Rule::exists('exercise_documents', 'id')],
            'school_class_id' => ['nullable', 'integer', Rule::exists('school_classes', 'id')],
            'replace_items' => ['nullable', 'boolean'],
        ];
    }
}
