<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MathLevelFinishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'attempt_id' => [
                'required',
                'integer',
                Rule::exists('math_level_attempts', 'id'),
            ],
        ];
    }
}
