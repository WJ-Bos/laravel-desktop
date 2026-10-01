<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GridRequest;
use App\Support\Grid\CsvExport;
use App\Support\Grid\GridQuery;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Base for every module grid: paging/sorting/filtering via GridQuery and a
 * CSV export that honours the same filters as the on-screen grid.
 *
 * @template TModel of Model
 */
abstract class GridController extends Controller
{
    /**
     * @return GridQuery<TModel>
     */
    abstract protected function grid(): GridQuery;

    /**
     * @return class-string<JsonResource>
     */
    abstract protected function resource(): string;

    /**
     * CSV header => attribute or resolver.
     *
     * @return array<string, string|Closure(TModel): mixed>
     */
    abstract protected function exportColumns(): array;

    abstract protected function exportName(): string;

    public function index(GridRequest $request): AnonymousResourceCollection
    {
        $page = $this->grid()->paginate($request);

        return $this->resource()::collection($page->rows)->additional(['meta' => $page->meta()]);
    }

    public function export(GridRequest $request): StreamedResponse
    {
        return CsvExport::download(
            $this->exportName().'-'.now()->format('Ymd-His').'.csv',
            $this->grid()->all($request),
            $this->exportColumns(),
        );
    }
}
