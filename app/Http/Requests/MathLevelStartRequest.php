<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MathLevelStartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'level_id' => [
                'required',
                'integer',
                Rule::exists('math_levels', 'id'),
            ],
        ];
    }
}
