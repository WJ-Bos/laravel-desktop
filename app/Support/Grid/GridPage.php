<?php

namespace App\Support\Grid;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
final readonly class GridPage
{
    /**
     * @param  Collection<int, TModel>  $rows
     */
    public function __construct(
        public Collection $rows,
        public int $total,
        public int $start,
        public int $end,
    ) {}

    /**
     * @return array{total: int, start: int, end: int, lastRow: int|null}
     */
    public function meta(): array
    {
        return [
            'total' => $this->total,
            'start' => $this->start,
            'end' => $this->start + $this->rows->count(),
            // AG Grid infinite row model: lastRow tells the grid where the data ends.
            'lastRow' => $this->total,
        ];
    }
}
