<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpsertOperatingHourRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'opens_at' => ['nullable', 'date_format:H:i', 'required_unless:is_closed,true'],
            'closes_at' => ['nullable', 'date_format:H:i', 'after:opens_at', 'required_unless:is_closed,true'],
            'is_closed' => ['required', 'boolean'],
        ];
    }
}
