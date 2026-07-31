<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates the criteria-condition-cause-effect finding record. */
class AemsFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'title',
            'criteria',
            'condition',
            'cause',
            'effect',
            'noRecommendationReason',
        ] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => $this->filled($field)
                        ? trim((string) $this->input($field))
                        : null,
                ]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'criteria' => ['required', 'string', 'min:3', 'max:20000'],
            'condition' => ['required', 'string', 'min:3', 'max:20000'],
            'cause' => ['required', 'string', 'min:3', 'max:20000'],
            'effect' => ['required', 'string', 'min:3', 'max:20000'],
            'noRecommendationReason' => ['nullable', 'string', 'min:5', 'max:4000'],
            'riskRatingId' => [
                'required',
                'integer',
                Rule::exists('master_list_items', 'id')->whereNull('deleted_at'),
            ],
            'responsibleOfficeId' => [
                'required',
                'integer',
                Rule::exists('offices', 'id')->whereNull('deleted_at'),
            ],
            'workingPaperVersionIds' => ['sometimes', 'array', 'max:100'],
            'workingPaperVersionIds.*' => ['integer', 'distinct'],
            'evidenceIds' => ['sometimes', 'array', 'max:100'],
            'evidenceIds.*' => ['integer', 'distinct'],
            'lockVersion' => [
                $this->route('finding') ? 'required' : 'nullable',
                'integer',
                'min:1',
            ],
        ];
    }
}
