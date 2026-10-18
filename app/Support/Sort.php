<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Column ordering for list pages: the table heading links carry ?sort=key&dir=asc|desc and the controller maps the key
 * to a safe column (or closure), so nothing from the URL ever reaches the query unchecked.
 */
final class Sort
{
    /**
     * @param  EloquentBuilder<*>|Builder  $query
     * @param  array<string, string|Closure>  $columns  sort key => column name, or fn ($query, string $direction) for joins/expressions
     */
    public static function apply(EloquentBuilder|Builder $query, Request $request, array $columns, string $defaultKey, string $defaultDirection = 'asc', ?string $tiebreak = null): void
    {
        self::applyKey($query, (string) $request->query('sort', ''), (string) $request->query('dir', ''), $columns, $defaultKey, $defaultDirection, $tiebreak);
    }

    /**
     * Same as apply(), for services that receive the requested key and direction as plain values.
     *
     * @param  EloquentBuilder<*>|Builder  $query
     * @param  array<string, string|Closure>  $columns
     */
    public static function applyKey(EloquentBuilder|Builder $query, ?string $requestedKey, ?string $requestedDirection, array $columns, string $defaultKey, string $defaultDirection = 'asc', ?string $tiebreak = null): void
    {
        [$key, $direction] = self::pick($requestedKey, $requestedDirection, $columns, $defaultKey, $defaultDirection);
        $target = $columns[$key];

        $query->reorder();

        if ($target instanceof Closure) {
            $target($query, $direction);
        } else {
            $query->orderBy($target, $direction);
        }

        if ($tiebreak !== null) {
            $query->orderBy($tiebreak, 'desc');
        }
    }

    /**
     * @param  array<string, string|Closure>  $columns
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public static function pick(?string $requestedKey, ?string $requestedDirection, array $columns, string $defaultKey, string $defaultDirection = 'asc'): array
    {
        $key = (string) $requestedKey;
        $direction = strtolower((string) $requestedDirection);

        if (! array_key_exists($key, $columns)) {
            return [$defaultKey, $defaultDirection === 'desc' ? 'desc' : 'asc'];
        }

        return [$key, $direction === 'desc' ? 'desc' : 'asc'];
    }
}
