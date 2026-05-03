<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTeacherExerciseDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'document_id' => ['nullable', 'integer', Rule::exists('exercise_documents', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'language' => ['required', Rule::in(['en', 'hy', 'fr', 'ru'])],
            'content_json' => ['nullable', 'array'],
            'content_html' => ['nullable', 'string'],
            'meta' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.item_order' => ['nullable', 'integer', 'min:1'],
            'items.*.type' => ['nullable', 'string', 'max:30'],
            'items.*.question' => ['required_with:items', 'string'],
            'items.*.question_html' => ['nullable', 'string'],
            'items.*.options' => ['nullable', 'array'],
            'items.*.options_html' => ['nullable', 'array'],
            'items.*.answer' => ['nullable', 'string'],
            'items.*.answer_html' => ['nullable', 'string'],
            'items.*.explanation' => ['nullable', 'string'],
            'items.*.explanation_html' => ['nullable', 'string'],
            'items.*.difficulty' => ['nullable', 'string', 'max:30'],
            'items.*.points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'items.*.source' => ['nullable', 'string', 'max:20'],
        ];
    }
}
