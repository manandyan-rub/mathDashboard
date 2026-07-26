<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMathWorkspaceCalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $topics = [
            'rectangle',
            'circle',
            'triangle',
            'trapezoid',
            'parallelogram',
            'cube',
        ];

        return [
            'title' => ['required', 'string', 'max:120'],
            'topic' => ['required', 'string', Rule::in($topics)],
            'problem_text' => ['nullable', 'string', 'max:2000'],
            'state' => ['required', 'array'],
            'state.version' => ['required', 'integer', Rule::in([1])],
            'state.selectedTopic' => ['required', 'string', Rule::in($topics), 'same:topic'],
            'state.solveFor' => ['nullable', 'string', 'max:80'],
            'state.userEnteredValues' => ['required', 'array', 'max:24'],
            'state.userEnteredValues.*' => ['required', 'array'],
            'state.userEnteredValues.*.key' => ['required', 'string', 'max:80'],
            'state.userEnteredValues.*.label' => ['required', 'string', 'max:120'],
            'state.userEnteredValues.*.symbol' => ['required', 'string', 'max:20'],
            'state.userEnteredValues.*.value' => ['required', 'numeric'],
            'state.userEnteredValues.*.dimension' => ['required', 'integer', 'between:0,3'],
            'state.userEnteredValues.*.source' => ['required', Rule::in(['entered'])],
            'state.userEnteredValues.*.unit' => ['required', 'string', 'max:20'],
            'state.calculatedValues' => ['required', 'array', 'max:32'],
            'state.calculatedValues.*' => ['required', 'array'],
            'state.calculatedValues.*.key' => ['required', 'string', 'max:80'],
            'state.calculatedValues.*.label' => ['required', 'string', 'max:120'],
            'state.calculatedValues.*.symbol' => ['required', 'string', 'max:20'],
            'state.calculatedValues.*.value' => ['required', 'numeric'],
            'state.calculatedValues.*.dimension' => ['required', 'integer', 'between:0,3'],
            'state.calculatedValues.*.source' => ['required', Rule::in(['calculated'])],
            'state.calculatedValues.*.unit' => ['required', 'string', 'max:20'],
            'state.units' => ['required', 'array'],
            'state.units.length' => ['required', Rule::in(['mm', 'cm', 'm', 'km', 'in', 'ft'])],
            'state.units.area' => ['required', 'string', 'max:20'],
            'state.units.volume' => ['required', 'string', 'max:20'],
            'state.units.angle' => ['required', 'string', 'max:20'],
            'state.formulas' => ['required', 'array', 'max:32'],
            'state.formulas.*' => ['required', 'array'],
            'state.formulas.*.key' => ['required', 'string', 'max:120'],
            'state.formulas.*.label' => ['required', 'string', 'max:120'],
            'state.formulas.*.expression' => ['required', 'string', 'max:500'],
            'state.formulas.*.resultKey' => ['required', 'string', 'max:80'],
            'state.calculationSteps' => ['required', 'array', 'max:40'],
            'state.calculationSteps.*' => ['required', 'array'],
            'state.calculationSteps.*.id' => ['required', 'string', 'max:120'],
            'state.calculationSteps.*.title' => ['required', 'string', 'max:160'],
            'state.calculationSteps.*.formula' => ['required', 'string', 'max:500'],
            'state.calculationSteps.*.substitution' => ['required', 'string', 'max:1000'],
            'state.calculationSteps.*.result' => ['required', 'string', 'max:500'],
            'state.calculationSteps.*.rule' => ['nullable', 'string', 'max:500'],
            'state.warnings' => ['present', 'array', 'max:24'],
            'state.warnings.*' => ['required', 'string', 'max:500'],
            'state.conflicts' => ['present', 'array', 'max:24'],
            'state.conflicts.*' => ['required', 'array'],
            'state.conflicts.*.field' => ['required', 'string', 'max:80'],
            'state.conflicts.*.label' => ['required', 'string', 'max:120'],
            'state.conflicts.*.enteredValue' => ['required', 'numeric'],
            'state.conflicts.*.expectedValue' => ['nullable', 'numeric'],
            'state.conflicts.*.message' => ['required', 'string', 'max:500'],
            'state.graphConfiguration' => ['nullable', 'array'],
            'state.graphConfiguration.title' => ['required_with:state.graphConfiguration', 'string', 'max:160'],
            'state.graphConfiguration.description' => ['required_with:state.graphConfiguration', 'string', 'max:300'],
            'state.graphConfiguration.labels' => ['required_with:state.graphConfiguration', 'array', 'max:12'],
            'state.graphConfiguration.labels.*' => ['required', 'string', 'max:120'],
            'state.graphConfiguration.values' => ['required_with:state.graphConfiguration', 'array', 'max:12'],
            'state.graphConfiguration.values.*' => ['required', 'numeric'],
            'state.graphConfiguration.unit' => ['required_with:state.graphConfiguration', 'string', 'max:20'],
            'state.visualizationConfiguration' => ['nullable', 'array'],
            'state.visualizationConfiguration.kind' => [
                'required_with:state.visualizationConfiguration',
                Rule::in(['geometry-2d', 'geometry-3d']),
            ],
            'state.visualizationConfiguration.shape' => [
                'required_with:state.visualizationConfiguration',
                Rule::in($topics),
                'same:state.selectedTopic',
            ],
            'state.visualizationConfiguration.values' => [
                'required_with:state.visualizationConfiguration',
                'array',
                'max:32',
            ],
            'state.visualizationConfiguration.values.*' => ['required', 'numeric'],
        ];
    }
}
