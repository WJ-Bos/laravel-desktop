<?php

namespace App\Support\Grid;

use App\Http\Requests\GridRequest;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Translates AG Grid's sort and filter models (plus a quick-search term and
 * a start/end window) into an Eloquent query. Every grid endpoint builds one
 * of these, so paging, sorting and filtering behave identically everywhere.
 *
 * Supported filter models:
 *  - text:   contains, notContains, equals, notEqual, startsWith, endsWith, blank, notBlank
 *  - number: equals, notEqual, lessThan(OrEqual), greaterThan(OrEqual), inRange, blank, notBlank
 *  - date:   equals, notEqual, lessThan, greaterThan, inRange, blank, notBlank
 *  - set:    { values: [...] } (used by toolbar status pickers)
 *  - boolean:{ filter: true|false }
 *  - combined conditions: { operator: AND|OR, conditions: [...] }
 *
 * @template TModel of Model
 */
final class GridQuery
{
    /** @var array<string, GridColumn> */
    private array $columns = [];

    /** @var list<literal-string> */
    private array $searchable = [];

    /** @var array{0: string, 1: 'asc'|'desc'}|null */
    private ?array $defaultSort = null;

    private string $tieBreaker;

    /**
     * @param  Builder<TModel>  $query
     */
    private function __construct(private Builder $query)
    {
        $this->tieBreaker = $query->getModel()->getQualifiedKeyName();
    }

    /**
     * @template TM of Model
     *
     * @param  Builder<TM>  $query
     * @return self<TM>
     */
    public static function for(Builder $query): self
    {
        return new self($query);
    }

    /**
     * @param  array<string, GridColumn>  $columns
     * @return $this
     */
    public function columns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Expressions matched (LIKE) by the quick-search box.
     *
     * @param  list<literal-string>  $expressions
     * @return $this
     */
    public function searchable(array $expressions): self
    {
        $this->searchable = $expressions;

        return $this;
    }

    /**
     * @param  'asc'|'desc'  $direction
     * @return $this
     */
    public function defaultSort(string $colId, string $direction = 'asc'): self
    {
        $this->defaultSort = [$colId, $direction];

        return $this;
    }

    /**
     * @return array<string, GridColumn>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Apply search, filters and sorting from the request.
     *
     * @return Builder<TModel>
     */
    public function build(GridRequest $request): Builder
    {
        $query = clone $this->query;

        $this->applySearch($query, $request->search());
        $this->applyFilters($query, $request->filterModel());
        $this->applySort($query, $request->sortModel());

        return $query;
    }

    /**
     * @return GridPage<TModel>
     */
    public function paginate(GridRequest $request): GridPage
    {
        $query = $this->build($request);

        $total = (clone $query)->toBase()->getCountForPagination();
        $start = $request->start();

        $rows = $query->skip($start)->take($request->limit())->get();

        return new GridPage($rows, $total, $start, $start + $request->limit());
    }

    /**
     * Every matching row (ignores the paging window), for CSV export.
     *
     * @return LazyCollection<int, TModel>
     */
    public function all(GridRequest $request): LazyCollection
    {
        return $this->build($request)->lazy(500);
    }

    /**
     * @param  Builder<TModel>  $query
     */
    private function applySearch(Builder $query, ?string $term): void
    {
        if ($term === null || $term === '' || $this->searchable === []) {
            return;
        }

        $like = '%'.$this->escapeLike($term).'%';

        $query->where(function (Builder $q) use ($like) {
            foreach ($this->searchable as $expression) {
                $this->like($q, $expression, $like, boolean: 'or');
            }
        });
    }

    /**
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $model
     */
    private function applyFilters(Builder $query, array $model): void
    {
        foreach ($model as $colId => $filter) {
            $column = $this->columns[$colId] ?? null;

            if ($column === null || ! $column->filterable || ! is_array($filter)) {
                continue;
            }

            $query->where(function (Builder $q) use ($column, $filter) {
                if (isset($filter['conditions']) && is_array($filter['conditions'])) {
                    $boolean = strtoupper((string) ($filter['operator'] ?? 'AND')) === 'OR' ? 'or' : 'and';

                    foreach ($filter['conditions'] as $condition) {
                        if (is_array($condition)) {
                            $q->where(fn (Builder $inner) => $this->applyCondition($inner, $column, $condition), boolean: $boolean);
                        }
                    }

                    return;
                }

                $this->applyCondition($q, $column, $filter);
            });
        }
    }

    /**
     * @param  Builder<TModel>  $q
     * @param  array<string, mixed>  $condition
     */
    private function applyCondition(Builder $q, GridColumn $column, array $condition): void
    {
        $expr = DB::raw($column->expression);
        $type = (string) ($condition['type'] ?? 'equals');

        if ($type === 'blank') {
            $q->whereNull($expr);
            if ($column->type === GridColumn::TEXT) {
                $q->orWhere($expr, '=', '');
            }

            return;
        }

        if ($type === 'notBlank') {
            $q->whereNotNull($expr);
            if ($column->type === GridColumn::TEXT) {
                $q->where($expr, '!=', '');
            }

            return;
        }

        match ($column->type) {
            GridColumn::NUMBER => $this->numberCondition($q, $column->expression, $type, $condition),
            GridColumn::DATE => $this->dateCondition($q, $expr, $type, $condition),
            GridColumn::SET => $this->setCondition($q, $expr, $condition),
            GridColumn::BOOLEAN => $q->where($expr, '=', filter_var($condition['filter'] ?? false, FILTER_VALIDATE_BOOL) ? 1 : 0),
            default => $this->textCondition($q, $column->expression, $type, $condition),
        };
    }

    /**
     * LIKE with an explicit ESCAPE so user-typed % and _ are matched literally.
     *
     * @param  Builder<TModel>  $q
     * @param  literal-string  $expression
     * @param  array<string, mixed>  $condition
     */
    private function textCondition(Builder $q, string $expression, string $type, array $condition): void
    {
        $value = $this->escapeLike((string) ($condition['filter'] ?? ''));

        match ($type) {
            'equals' => $this->like($q, $expression, $value),
            'notEqual' => $q->where(fn (Builder $w) => $this->like($w, $expression, $value, not: true)->orWhereNull(DB::raw($expression))),
            'startsWith' => $this->like($q, $expression, $value.'%'),
            'endsWith' => $this->like($q, $expression, '%'.$value),
            'notContains' => $q->where(fn (Builder $w) => $this->like($w, $expression, '%'.$value.'%', not: true)->orWhereNull(DB::raw($expression))),
            default => $this->like($q, $expression, '%'.$value.'%'),
        };
    }

    /**
     * @param  Builder<TModel>  $q
     * @param  literal-string  $expression
     * @return Builder<TModel>
     */
    private function like(Builder $q, string $expression, string $pattern, bool $not = false, string $boolean = 'and'): Builder
    {
        $operator = $not ? 'NOT LIKE' : 'LIKE';

        return $q->whereRaw("{$expression} {$operator} ? ESCAPE '\\'", [$pattern], $boolean);
    }

    /**
     * @param  Builder<TModel>  $q
     * @param  literal-string  $expr
     * @param  array<string, mixed>  $condition
     */
    private function numberCondition(Builder $q, string $expr, string $type, array $condition): void
    {
        $value = is_numeric($condition['filter'] ?? null) ? (float) $condition['filter'] : null;
        $to = is_numeric($condition['filterTo'] ?? null) ? (float) $condition['filterTo'] : null;

        if ($value === null) {
            return;
        }

        // Bind through an explicit numeric cast: computed expressions have no
        // column affinity in SQLite, so a string-bound "375" would never equal 375.
        $num = 'cast(? as decimal(20,6))';

        match ($type) {
            'notEqual' => $q->whereRaw("{$expr} != {$num}", [$value]),
            'lessThan' => $q->whereRaw("{$expr} < {$num}", [$value]),
            'lessThanOrEqual' => $q->whereRaw("{$expr} <= {$num}", [$value]),
            'greaterThan' => $q->whereRaw("{$expr} > {$num}", [$value]),
            'greaterThanOrEqual' => $q->whereRaw("{$expr} >= {$num}", [$value]),
            'inRange' => $q->whereRaw(
                "{$expr} between {$num} and {$num}",
                [min($value, $to ?? $value), max($value, $to ?? $value)],
            ),
            default => $q->whereRaw("{$expr} = {$num}", [$value]),
        };
    }

    /**
     * @param  Builder<TModel>  $q
     * @param  array<string, mixed>  $condition
     */
    private function dateCondition(Builder $q, Expression $expr, string $type, array $condition): void
    {
        $from = $this->parseDate($condition['dateFrom'] ?? null);
        $to = $this->parseDate($condition['dateTo'] ?? null);

        if ($from === null) {
            return;
        }

        match ($type) {
            'notEqual' => $q->whereDate($expr, '!=', $from),
            'lessThan' => $q->whereDate($expr, '<', $from),
            'greaterThan' => $q->whereDate($expr, '>', $from),
            'inRange' => $q->whereDate($expr, '>=', $from)->whereDate($expr, '<=', $to ?? $from),
            default => $q->whereDate($expr, '=', $from),
        };
    }

    /**
     * @param  Builder<TModel>  $q
     * @param  array<string, mixed>  $condition
     */
    private function setCondition(Builder $q, Expression $expr, array $condition): void
    {
        $values = $condition['values'] ?? null;

        if (! is_array($values)) {
            $values = isset($condition['filter']) ? [(string) $condition['filter']] : [];
        }

        $values = array_values(array_filter($values, fn ($v) => is_scalar($v)));

        if ($values === []) {
            // An empty selection matches nothing, mirroring AG Grid's set-filter semantics.
            $q->whereRaw('1 = 0');

            return;
        }

        $q->whereIn($expr, $values);
    }

    /**
     * @param  Builder<TModel>  $query
     * @param  list<array{colId: string, sort: string}>  $sortModel
     */
    private function applySort(Builder $query, array $sortModel): void
    {
        $applied = false;

        foreach ($sortModel as $sort) {
            $column = $this->columns[$sort['colId']] ?? null;

            if ($column === null || ! $column->sortable) {
                continue;
            }

            $query->orderBy(DB::raw($column->expression), $sort['sort'] === 'desc' ? 'desc' : 'asc');
            $applied = true;
        }

        if (! $applied && $this->defaultSort !== null && isset($this->columns[$this->defaultSort[0]])) {
            $query->orderBy(DB::raw($this->columns[$this->defaultSort[0]]->expression), $this->defaultSort[1]);
        }

        // Stable ordering so consecutive blocks never overlap or skip rows.
        $query->orderBy($this->tieBreaker, $this->defaultSort[1] ?? 'asc');
    }

    private function parseDate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
