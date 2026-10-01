<?php

namespace App\Http\Requests;

use App\Enums\DeviceStatus;
use App\Models\Gateway;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveGatewayRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('serial'))) {
            $this->merge(['serial' => strtoupper(trim($this->input('serial')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $gateway = $this->route('gateway');

        return [
            'headend_id' => ['required', 'integer', Rule::exists('headends', 'id')],
            'serial' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('gateways', 'serial')->ignore($gateway instanceof Gateway ? $gateway->id : null)],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'comms' => ['required', 'string', 'in:HD-PLC/RF,LTE,RS-485,Ethernet'],
            'firmware' => ['nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+){1,3}$/'],
            'signal_dbm' => ['nullable', 'integer', 'between:-130,0'],
            'status' => ['required', Rule::enum(DeviceStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['headend_id' => 'headend', 'signal_dbm' => 'signal strength'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'serial.regex' => 'The serial may only contain letters, numbers and dashes.',
            'firmware.regex' => 'Use a version number like 4.3.2.',
        ];
    }
}
