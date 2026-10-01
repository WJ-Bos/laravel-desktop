<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Driver-aware SQL snippets for date bucketing, so aggregate queries work on
 * SQLite (the demo default) as well as MySQL/MariaDB and PostgreSQL.
 */
final class SqlDate
{
    public static function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    /**
     * Expression yielding a 'Y-m-d' (or 'Y-m-d H:00' for hours) bucket start for the given granularity.
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    public static function bucket(string $column, string $granularity): string
    {
        return match (self::driver()) {
            'mysql', 'mariadb' => match ($granularity) {
                'hour' => "DATE_FORMAT({$column}, '%Y-%m-%d %H:00')",
                'month' => "DATE_FORMAT({$column}, '%Y-%m-01')",
                'week' => "DATE_FORMAT(DATE_SUB(DATE({$column}), INTERVAL WEEKDAY({$column}) DAY), '%Y-%m-%d')",
                default => "DATE_FORMAT({$column}, '%Y-%m-%d')",
            },
            'pgsql' => match ($granularity) {
                'hour' => "to_char({$column}, 'YYYY-MM-DD HH24:00')",
                'month' => "to_char(date_trunc('month', {$column}), 'YYYY-MM-DD')",
                'week' => "to_char(date_trunc('week', {$column}), 'YYYY-MM-DD')",
                default => "to_char({$column}, 'YYYY-MM-DD')",
            },
            default => match ($granularity) {
                'hour' => "strftime('%Y-%m-%d %H:00', {$column})",
                'month' => "strftime('%Y-%m-01', {$column})",
                'week' => "date({$column}, 'weekday 0', '-6 days')",
                default => "date({$column})",
            },
        };
    }

    /**
     * Day of week with Monday = 0 … Sunday = 6.
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    public static function weekday(string $column): string
    {
        return match (self::driver()) {
            'mysql', 'mariadb' => "WEEKDAY({$column})",
            'pgsql' => "(extract(isodow from {$column})::int - 1)",
            default => "((cast(strftime('%w', {$column}) as integer) + 6) % 7)",
        };
    }

    /**
     * @param  literal-string  $column
     * @return literal-string
     */
    public static function hour(string $column): string
    {
        return match (self::driver()) {
            'mysql', 'mariadb' => "HOUR({$column})",
            'pgsql' => "extract(hour from {$column})::int",
            default => "cast(strftime('%H', {$column}) as integer)",
        };
    }

    /**
     * @param  literal-string  $later
     * @param  literal-string  $earlier
     * @return literal-string
     */
    public static function daysBetween(string $later, string $earlier): string
    {
        return match (self::driver()) {
            'mysql', 'mariadb' => "DATEDIFF({$later}, {$earlier})",
            'pgsql' => "({$later}::date - {$earlier}::date)",
            default => "cast(julianday({$later}) - julianday({$earlier}) as integer)",
        };
    }

    /**
     * The timestamp 30 days before now, as SQL.
     *
     * @return literal-string
     */
    public static function thirtyDaysAgo(): string
    {
        return match (self::driver()) {
            'mysql', 'mariadb' => '(NOW() - INTERVAL 30 DAY)',
            'pgsql' => "(now() - interval '30 days')",
            default => "datetime('now', '-30 days')",
        };
    }

    /**
     * Cut-off for "reporting": a meter that delivered a reading in the last two hours.
     *
     * @return literal-string
     */
    public static function reportingCutoff(): string
    {
        return match (self::driver()) {
            'mysql', 'mariadb' => '(NOW() - INTERVAL 2 HOUR)',
            'pgsql' => "(now() - interval '2 hours')",
            default => "datetime('now', '-2 hours')",
        };
    }
}
