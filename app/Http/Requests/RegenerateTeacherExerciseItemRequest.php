<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegenerateTeacherExerciseItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'document_id' => ['required', 'integer', Rule::exists('exercise_documents', 'id')],
            'item_id' => ['required', 'integer', Rule::exists('exercise_items', 'id')],
            'topic' => ['nullable', 'string', 'max:255'],
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'language' => ['nullable', Rule::in(['en', 'hy', 'fr', 'ru'])],
            'instructions' => ['nullable', 'string', 'max:1500'],
        ];
    }
}

