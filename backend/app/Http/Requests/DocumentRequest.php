<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Models\MasterList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'referenceNumber', 'issuingAuthority', 'version', 'description'] as $field) {
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
        /** @var Document|null $document */
        $document = $this->route('document');
        $documentTypeListId = MasterList::query()
            ->where('code', 'DOCUMENT_TYPE')
            ->value('id');

        return [
            'documentTypeId' => [
                'required',
                'integer',
                Rule::exists('master_list_items', 'id')
                    ->where('master_list_id', $documentTypeListId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'referenceNumber' => ['nullable', 'string', 'max:120'],
            'issuingAuthority' => ['nullable', 'string', 'max:255'],
            'publicationDate' => ['nullable', 'date'],
            'version' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:3000'],
            'isActive' => ['sometimes', 'boolean'],
            'file' => [
                $document ? 'nullable' : 'required',
                'file',
                'max:25600',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv',
            ],
        ];
    }
}
