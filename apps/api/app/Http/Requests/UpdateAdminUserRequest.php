<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true && $this->user()->suspended_at === null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'role' => ['sometimes', Rule::in(['customer', 'counter_staff', 'branch_manager', 'super_admin'])],
            'suspended' => ['sometimes', 'boolean'],
            'reason' => ['required_with:name,suspended,role', 'string', 'min:4', 'max:255'],
        ];
    }
}
