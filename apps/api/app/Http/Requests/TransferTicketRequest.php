<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['target_counter_id' => ['required', 'integer', 'exists:counters,id'], 'reason' => ['required', 'string', 'min:5', 'max:255']];
    }
}
