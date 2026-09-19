<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required', 'alpha_dash', 'max:20',
                Rule::unique('services')->where('branch_id', $this->route('branch')->id),
            ],
            'average_service_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
