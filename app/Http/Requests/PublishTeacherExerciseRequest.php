<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishTeacherExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'document_id' => ['required', 'integer', Rule::exists('exercise_documents', 'id')],
            'school_class_id' => ['nullable', 'integer', Rule::exists('school_classes', 'id')],
            'title' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', Rule::in(['en', 'hy', 'fr', 'ru'])],
            'content_json' => ['nullable', 'array'],
            'content_html' => ['nullable', 'string'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
