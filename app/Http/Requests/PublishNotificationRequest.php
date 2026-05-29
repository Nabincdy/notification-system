<?php


namespace App\Http\Requests;

use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PublishNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'min:1'],
            'user_id'   => ['required', 'integer', 'min:1'],
            'type'      => ['required', 'string', Rule::enum(NotificationType::class)],
            'title'     => ['required', 'string', 'max:255'],
            'message'   => ['required', 'string', 'max:5000'],
            'metadata'  => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'The type must be one of: ' . implode(', ', array_column(NotificationType::cases(), 'value')),
        ];
    }
}
