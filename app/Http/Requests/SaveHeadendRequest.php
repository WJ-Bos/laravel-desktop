<?php

namespace App\Http\Requests;

use App\Enums\DeviceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveHeadendRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'site' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'protocol' => ['required', 'string', 'max:30'],
            'ip_address' => ['nullable', 'ip'],
            'poll_interval_min' => ['required', 'integer', 'in:5,15,30,60'],
            'status' => ['required', Rule::enum(DeviceStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'poll_interval_min.in' => 'The poll interval must be 5, 15, 30 or 60 minutes.',
        ];
    }
}
