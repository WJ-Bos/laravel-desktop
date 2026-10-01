<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query parameters shared by every grid endpoint.
 *
 *   start, end   row window requested by AG Grid's infinite row model
 *   sort         AG Grid sort model, JSON or array: [{colId, sort}]
 *   filter       AG Grid filter model, JSON or array: {colId: {...}}
 *   q            quick-search term
 */
class GridRequest extends FormRequest
{
    public const MAX_WINDOW = 1000;

    public const DEFAULT_WINDOW = 100;

    protected function prepareForValidation(): void
    {
        if (! $this->filled('start')) {
            $this->merge(['start' => 0]);
        }

        foreach (['sort', 'filter'] as $key) {
            $value = $this->input($key);

            if (is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);
                $this->merge([$key => is_array($decoded) ? $decoded : '__invalid__']);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start' => ['nullable', 'integer', 'min:0'],
            'end' => ['nullable', 'integer', 'gt:start', 'lte:'.((int) $this->input('start', 0) + self::MAX_WINDOW)],
            'sort' => ['nullable', 'array', 'max:5'],
            'sort.*.colId' => ['required', 'string', 'max:64'],
            'sort.*.sort' => ['required', 'in:asc,desc'],
            'filter' => ['nullable', 'array', 'max:30'],
            'q' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sort.array' => 'The sort model must be a JSON array.',
            'filter.array' => 'The filter model must be a JSON object.',
        ];
    }

    public function start(): int
    {
        return (int) $this->validated('start', 0);
    }

    public function limit(): int
    {
        $end = $this->validated('end');

        return $end === null ? self::DEFAULT_WINDOW : (int) $end - $this->start();
    }

    /**
     * @return list<array{colId: string, sort: string}>
     */
    public function sortModel(): array
    {
        /** @var list<array{colId: string, sort: string}> $sort */
        $sort = array_values($this->validated('sort') ?? []);

        return $sort;
    }

    /**
     * @return array<string, mixed>
     */
    public function filterModel(): array
    {
        /** @var array<string, mixed> $filter */
        $filter = $this->validated('filter') ?? [];

        return $filter;
    }

    public function search(): ?string
    {
        $q = $this->validated('q');

        return is_string($q) ? trim($q) : null;
    }
}
