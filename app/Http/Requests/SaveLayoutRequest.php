<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveLayoutRequest extends FormRequest
{
    public const MAX_BYTES = 262144;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'data' => ['required', 'array'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $encoded = json_encode($this->input('data'));

                if ($encoded !== false && strlen($encoded) > self::MAX_BYTES) {
                    $validator->errors()->add('data', 'The layout is too large to save (max 256 KB).');
                }
            },
        ];
    }
}
