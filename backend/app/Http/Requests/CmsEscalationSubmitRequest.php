<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CmsEscalationSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['lockVersion' => ['required', 'integer', 'min:1'], 'confirmation' => ['accepted']];
    }
}
