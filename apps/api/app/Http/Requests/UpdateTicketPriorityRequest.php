<?php

namespace App\Http\Requests;

use App\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['priority' => ['required', Rule::enum(TicketPriority::class)], 'reason' => ['required', 'string', 'min:5', 'max:255']];
    }
}
