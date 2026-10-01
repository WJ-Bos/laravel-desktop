<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * from/to date range used by the dashboard and reports. Defaults to the
 * last 14 days ending today.
 */
class DateRangeRequest extends FormRequest
{
    public const MAX_DAYS = 1100;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->from()->diffInDays($this->to()) > self::MAX_DAYS) {
                    $validator->errors()->add('from', 'The date range may not exceed three years.');
                }
            },
        ];
    }

    public function from(): CarbonImmutable
    {
        $from = $this->input('from');

        return is_string($from) && $from !== ''
            ? CarbonImmutable::createFromFormat('Y-m-d', $from)?->startOfDay() ?? $this->to()->subDays(13)
            : $this->to()->subDays(13)->startOfDay();
    }

    public function to(): CarbonImmutable
    {
        $to = $this->input('to');

        return is_string($to) && $to !== ''
            ? CarbonImmutable::createFromFormat('Y-m-d', $to)?->endOfDay() ?? CarbonImmutable::today()->endOfDay()
            : CarbonImmutable::today()->endOfDay();
    }
}
