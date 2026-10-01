<?php

namespace App\Support\Grid;

/**
 * Server-side description of one grid column: the SQL expression it maps to
 * and which filter semantics apply. Only columns registered on a GridQuery
 * can be sorted or filtered, which keeps client input away from raw SQL.
 */
final readonly class GridColumn
{
    public const TEXT = 'text';

    public const NUMBER = 'number';

    public const DATE = 'date';

    public const SET = 'set';

    public const BOOLEAN = 'boolean';

    /**
     * @param  literal-string  $expression
     */
    public function __construct(
        public string $expression,
        public string $type = self::TEXT,
        public bool $sortable = true,
        public bool $filterable = true,
    ) {}

    /**
     * @param  literal-string  $expression
     */
    public static function text(string $expression): self
    {
        return new self($expression, self::TEXT);
    }

    /**
     * @param  literal-string  $expression
     */
    public static function number(string $expression): self
    {
        return new self($expression, self::NUMBER);
    }

    /**
     * @param  literal-string  $expression
     */
    public static function date(string $expression): self
    {
        return new self($expression, self::DATE);
    }

    /**
     * @param  literal-string  $expression
     */
    public static function set(string $expression): self
    {
        return new self($expression, self::SET);
    }

    /**
     * @param  literal-string  $expression
     */
    public static function boolean(string $expression): self
    {
        return new self($expression, self::BOOLEAN);
    }

    public function unsortable(): self
    {
        return new self($this->expression, $this->type, false, $this->filterable);
    }
}
