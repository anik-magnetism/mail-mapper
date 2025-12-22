<?php

namespace AnikNinja\MailMapper\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmailMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // delegate auth to middleware
    }

    public function rules(): array
    {
        return [
            'module' => 'required|string|max:50',
            'menu' => 'required|string|max:100',
            'task' => 'required|string|max:50',

            'to' => 'required|array|min:1',
            'to.*' => 'email',

            'cc' => 'nullable|array',
            'cc.*' => 'email',

            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',

            'is_active' => 'boolean',
        ];
    }
}
