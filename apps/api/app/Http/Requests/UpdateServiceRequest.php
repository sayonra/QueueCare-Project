<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'code' => [
                'sometimes', 'required', 'alpha_dash', 'max:20',
                Rule::unique('services')->where('branch_id', $this->route('branch')->id)->ignore($this->route('service')),
            ],
            'average_service_minutes' => ['sometimes', 'required', 'integer', 'min:1', 'max:480'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
