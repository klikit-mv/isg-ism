<?php

namespace App\Support;

/**
 * Decimal money helpers. Values are handled as integer cents internally so
 * nothing is ever stored from a float.
 */
final class Money
{
    public static function toCents(string|int|float|null $value): int
    {
        $string = trim((string) ($value ?? '0'));

        if ($string === '' || ! is_numeric($string)) {
            return 0;
        }

        $negative = str_starts_with($string, '-');
        $string = ltrim($string, '+-');
        [$whole, $fraction] = array_pad(explode('.', $string, 2), 2, '');
        $fraction = substr(str_pad($fraction, 3, '0'), 0, 3);
        $cents = ((int) $whole) * 100 + intdiv((int) $fraction, 10) + (((int) $fraction % 10) >= 5 ? 1 : 0);

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function normalize(string|int|float|null $value): string
    {
        return self::fromCents(self::toCents($value));
    }

    public static function add(string|int|float|null ...$values): string
    {
        return self::fromCents(array_sum(array_map(self::toCents(...), $values)));
    }

    public static function sub(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::fromCents(self::toCents($a) - self::toCents($b));
    }

    public static function mul(string|int|float|null $amount, int $quantity): string
    {
        return self::fromCents(self::toCents($amount) * $quantity);
    }

    public static function compare(string|int|float|null $a, string|int|float|null $b): int
    {
        return self::toCents($a) <=> self::toCents($b);
    }

    public static function min(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::compare($a, $b) <= 0 ? self::normalize($a) : self::normalize($b);
    }

    public static function max(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::compare($a, $b) >= 0 ? self::normalize($a) : self::normalize($b);
    }

    public static function isPositive(string|int|float|null $value): bool
    {
        return self::toCents($value) > 0;
    }

    public static function format(string|int|float|null $value): string
    {
        $cents = self::toCents($value);

        return config('scout.currency_symbol', 'MVR').' '.number_format($cents / 100, 2);
    }

    public static function compact(string|int|float|null $value): string
    {
        $amount = self::toCents($value) / 100;

        return match (true) {
            abs($amount) >= 1_000_000 => number_format($amount / 1_000_000, 1).'M',
            abs($amount) >= 10_000 => number_format($amount / 1_000, 1).'k',
            default => number_format($amount, 2),
        };
    }
}
