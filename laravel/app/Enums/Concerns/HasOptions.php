<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * Case-insensitive lookup by value or label.
     */
    public static function fromLoose(?string $value): ?self
    {
        $needle = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $value));

        if ($needle === '') {
            return null;
        }

        foreach (self::cases() as $case) {
            $candidates = [$case->value, $case->label(), $case->name];

            foreach ($candidates as $candidate) {
                if (strtolower(preg_replace('/[^a-z0-9]/i', '', $candidate)) === $needle) {
                    return $case;
                }
            }
        }

        return null;
    }

    public function label(): string
    {
        return method_exists($this, 'labelText') ? $this->labelText() : ucwords(str_replace('_', ' ', $this->value));
    }

    public function badgeClasses(): string
    {
        $tone = method_exists($this, 'tone') ? $this->tone() : 'gray';

        return match ($tone) {
            'green' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
            'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'red' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
            'blue' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
            'purple' => 'bg-navy-100 text-navy-800 dark:bg-navy-900/60 dark:text-navy-200',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        };
    }
}
