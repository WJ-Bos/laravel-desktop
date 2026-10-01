<?php

namespace App\Http\Requests;

use App\Enums\MeterStatus;
use App\Enums\MeterType;
use App\Models\Meter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveMeterRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $meter = $this->route('meter');

        return [
            'gateway_id' => ['required', 'integer', Rule::exists('gateways', 'id')],
            'serial' => ['required', 'string', 'regex:/^\d{6,20}$/', Rule::unique('meters', 'serial')->ignore($meter instanceof Meter ? $meter->id : null)],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'account' => ['nullable', 'string', 'max:40'],
            'type' => ['required', Rule::enum(MeterType::class)],
            'manufacturer' => ['required', 'string', 'max:40'],
            'model' => ['required', 'string', 'max:40'],
            'accuracy_class' => ['required', 'in:0.2S,0.5S,1,2'],
            'ct_ratio' => ['nullable', 'string', 'regex:/^\d+\/[15]$/'],
            'vt_ratio' => ['nullable', 'string', 'regex:/^\d+\/\d+$/'],
            'tariff' => ['nullable', 'string', 'max:40'],
            'has_generation' => ['boolean'],
            'status' => ['required', Rule::enum(MeterStatus::class)],
            'installed_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['gateway_id' => 'gateway', 'ct_ratio' => 'CT ratio', 'vt_ratio' => 'VT ratio'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'serial.regex' => 'The serial must be 6 to 20 digits.',
            'ct_ratio.regex' => 'Use a ratio like 400/5 (secondary 1 or 5 A).',
            'vt_ratio.regex' => 'Use a ratio like 11000/110.',
        ];
    }

    /**
     * CT and CT/VT meters need their transformer ratios; direct meters must not have them.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $type = MeterType::tryFrom((string) $this->input('type'));

                if ($type === null) {
                    return;
                }

                $needsCt = in_array($type, [MeterType::Ct, MeterType::CtVt], true);

                if ($needsCt && ! $this->filled('ct_ratio')) {
                    $validator->errors()->add('ct_ratio', "A {$type->label()} meter needs a CT ratio.");
                }

                if (! $needsCt && $this->filled('ct_ratio')) {
                    $validator->errors()->add('ct_ratio', "{$type->label()} meters are direct-connected and have no CT ratio.");
                }

                if ($type === MeterType::CtVt && ! $this->filled('vt_ratio')) {
                    $validator->errors()->add('vt_ratio', 'A CT/VT meter needs a VT ratio.');
                }
            },
        ];
    }
}
