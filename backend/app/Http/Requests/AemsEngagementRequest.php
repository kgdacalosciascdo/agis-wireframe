<?php

namespace App\Http\Requests;

use App\Models\AuditEngagement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates mutable special-engagement and registry fields; IAP snapshots are
 * populated only by the server-side importer.
 */
class AemsEngagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'engagementCode' => $this->filled('engagementCode')
                ? strtoupper(trim((string) $this->input('engagementCode')))
                : null,
            'title' => trim((string) $this->input('title')),
            'specialAuthorityReference' => $this->filled('specialAuthorityReference')
                ? strtoupper(trim((string) $this->input('specialAuthorityReference')))
                : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var AuditEngagement|null $engagement */
        $engagement = $this->route('engagement');
        $creating = $this->isMethod('post');

        return [
            'engagementCode' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('audit_engagements', 'engagement_code')
                    ->ignore($engagement?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'specialAuthorityReference' => [
                Rule::requiredIf($creating),
                'nullable',
                'string',
                'max:100',
            ],
            'specialAuthorityTypeCode' => ['nullable', 'string', 'max:60'],
            'specialAuthorityDate' => [Rule::requiredIf($creating), 'nullable', 'date'],
            'specialAuthorityApprovedBy' => [
                Rule::requiredIf($creating),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'auditTypeId' => ['nullable', 'integer', 'exists:master_list_items,id'],
            'engagementApproachId' => ['nullable', 'integer', 'exists:master_list_items,id'],
            'background' => ['nullable', 'string', 'max:10000'],
            'objectives' => ['required', 'string', 'max:10000'],
            'scope' => ['required', 'string', 'max:10000'],
            'exclusions' => ['nullable', 'string', 'max:10000'],
            'plannedStartDate' => ['required', 'date'],
            'plannedEndDate' => ['required', 'date', 'after_or_equal:plannedStartDate'],
            'expectedReportDate' => ['nullable', 'date', 'after_or_equal:plannedEndDate'],
            'plannedPersonDays' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'officeIds' => ['required', 'array', 'min:1'],
            'officeIds.*' => [
                'integer',
                'distinct',
                Rule::exists('offices', 'id')->whereNull('deleted_at'),
            ],
            'auditAreaIds' => ['required', 'array', 'min:1'],
            'auditAreaIds.*' => [
                'integer',
                'distinct',
                Rule::exists('audit_areas', 'id')->whereNull('deleted_at'),
            ],
            'auditFocusIds' => ['sometimes', 'array'],
            'auditFocusIds.*' => [
                'integer',
                'distinct',
                Rule::exists('audit_focuses', 'id')->whereNull('deleted_at'),
            ],
            'lockVersion' => [$creating ? 'sometimes' : 'required', 'integer', 'min:1'],
        ];
    }
}
