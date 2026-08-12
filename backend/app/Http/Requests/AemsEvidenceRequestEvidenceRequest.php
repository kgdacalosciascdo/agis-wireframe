<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AemsEvidenceRequestEvidenceRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'evidenceId' => ['required', 'integer', Rule::exists('audit_evidence', 'id')->whereNull('deleted_at')],
            'documentVersionId' => ['required', 'integer', Rule::exists('document_versions', 'id')],
            'receiptNotes' => ['nullable', 'string', 'max:4000'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
