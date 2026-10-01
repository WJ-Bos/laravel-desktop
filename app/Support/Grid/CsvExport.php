<?php

namespace App\Support\Grid;

use BackedEnum;
use Carbon\CarbonInterface;
use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams rows to the browser as CSV without holding them all in memory.
 */
final class CsvExport
{
    /**
     * @template TRow
     *
     * @param  iterable<TRow>  $rows
     * @param  array<string, string|Closure(TRow): mixed>  $columns  header => attribute name or resolver
     */
    public static function download(string $filename, iterable $rows, array $columns): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $columns) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // UTF-8 BOM so Excel opens accented names correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_keys($columns), escape: '');

            foreach ($rows as $row) {
                $line = [];
                foreach ($columns as $resolver) {
                    $value = $resolver instanceof Closure ? $resolver($row) : data_get($row, $resolver);
                    $line[] = self::scalar($value);
                }
                fputcsv($out, $line, escape: '');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private static function scalar(mixed $value): string|int|float|null
    {
        return match (true) {
            $value === null => null,
            $value instanceof BackedEnum => $value->value,
            $value instanceof CarbonInterface => $value->toDateTimeString(),
            is_bool($value) => $value ? 'Yes' : 'No',
            is_int($value), is_float($value) => $value,
            is_scalar($value) => (string) $value,
            default => json_encode($value) ?: null,
        };
    }
}
