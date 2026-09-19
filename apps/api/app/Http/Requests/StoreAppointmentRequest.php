<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'customer';
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'scheduled_for' => ['required', 'date', 'after:now'],
            'visitors_count' => ['required', 'integer', 'between:1,5'],
        ];
    }
}
