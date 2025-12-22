<?php

namespace AnikNinja\MailMapper\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // delegate auth to middleware
    }

    public function rules(): array
    {
        return [
            'module' => 'sometimes|string|max:50',
            'menu' => 'sometimes|string|max:100',
            'task' => 'sometimes|string|max:50',

            'to' => 'sometimes|array|min:1',
            'to.*' => 'email',

            'cc' => 'sometimes|array',
            'cc.*' => 'email',

            'subject' => 'sometimes|nullable|string|max:255',
            'body' => 'sometimes|nullable|string',

            'is_active' => 'sometimes|boolean',
        ];
    }
}
