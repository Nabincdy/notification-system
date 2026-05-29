<?php


namespace App\Http\Requests;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'    => ['nullable', 'string', Rule::enum(NotificationStatus::class)],
            'type'      => ['nullable', 'string', Rule::enum(NotificationType::class)],
            'tenant_id' => ['nullable', 'integer', 'min:1'],
            'user_id'   => ['nullable', 'integer', 'min:1'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'      => ['nullable', 'integer', 'min:1'],
        ];
    }
}
