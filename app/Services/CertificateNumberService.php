<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Certificate;
use App\Models\CertificateCounter;
use Illuminate\Support\Facades\DB;

/**
 * Yearly sequences (organisation timezone) behind row-locked counters.
 */
class CertificateNumberService
{
    public function currentYear(): int
    {
        return (int) scout_now()->format('Y');
    }

    /**
     * @return array{counter: string, prefix: string, width: int}
     */
    public function badgeSequence(Badge $badge, ?int $year = null): array
    {
        $year ??= $this->currentYear();

        if ($badge->isSectionProficiency()) {
            return [
                'counter' => 'badge:proficiency:'.$badge->section->value.':'.$year,
                'prefix' => app(SettingsService::class)->sectionBadgeCode($badge->section),
                'width' => 3,
            ];
        }

        return [
            'counter' => 'badge:'.$badge->badge_id.':'.$year,
            'prefix' => strtoupper((string) ($badge->number_prefix ?: $badge->code)),
            'width' => 3,
        ];
    }

    public function nextBadgeNumber(Badge $badge): string
    {
        $year = $this->currentYear();
        $sequence = $this->badgeSequence($badge, $year);

        return $this->format($sequence['prefix'], $year, $this->nextSequence($sequence['counter'], $year, $sequence['prefix'], $badge->id, $sequence['width']), $sequence['width']);
    }

    public function peekBadgeNumber(Badge $badge): string
    {
        $year = $this->currentYear();
        $sequence = $this->badgeSequence($badge, $year);

        return $this->format($sequence['prefix'], $year, $this->peekSequence($sequence['counter']), $sequence['width']);
    }

    public function nextGeneralNumber(): string
    {
        $year = $this->currentYear();

        return $this->format('CERT', $year, $this->nextSequence('general:'.$year, $year, 'CERT'));
    }

    public function peekGeneralNumber(): string
    {
        $year = $this->currentYear();

        return $this->format('CERT', $year, $this->peekSequence('general:'.$year));
    }

    public function nextLeadershipNumber(): string
    {
        $year = $this->currentYear();

        return $this->format('LEAD', $year, $this->nextSequence('leadership:'.$year, $year, 'LEAD'));
    }

    public function peekLeadershipNumber(): string
    {
        $year = $this->currentYear();

        return $this->format('LEAD', $year, $this->peekSequence('leadership:'.$year));
    }

    /**
     * Take the next number, skipping any already used on a certificate.
     */
    public function nextSequence(string $counterId, int $year, string $prefix, ?int $badgeId = null, int $width = 3): int
    {
        return DB::transaction(function () use ($counterId, $year, $prefix, $badgeId, $width): int {
            CertificateCounter::query()->firstOrCreate(['counter_id' => $counterId], ['year' => $year, 'badge_id' => $badgeId, 'last_number' => 0]);
            $counter = CertificateCounter::query()->where('counter_id', $counterId)->lockForUpdate()->firstOrFail();

            $next = $counter->last_number + 1;

            while (Certificate::query()->where('cert_number', $this->format($prefix, $year, $next, $width))->exists()) {
                $next++;
            }

            $counter->update(['last_number' => $next]);

            return $next;
        });
    }

    public function peekSequence(string $counterId): int
    {
        return (int) (CertificateCounter::query()->where('counter_id', $counterId)->value('last_number') ?? 0) + 1;
    }

    /**
     * Let an admin choose the next number for a sequence.
     */
    public function setNextSequence(string $counterId, int $next, ?int $badgeId = null): void
    {
        $year = (int) substr($counterId, strrpos($counterId, ':') + 1);

        DB::transaction(function () use ($counterId, $next, $badgeId, $year): void {
            CertificateCounter::query()->firstOrCreate(['counter_id' => $counterId], ['year' => $year, 'badge_id' => $badgeId, 'last_number' => 0]);
            CertificateCounter::query()->where('counter_id', $counterId)->lockForUpdate()->update(['last_number' => max(0, $next - 1)]);
        });
    }

    /**
     * Every certificate number starts with the organisation code (FLHSG-).
     */
    public function withOrganisationCode(string $prefix): string
    {
        $org = strtoupper((string) config('scout.certificate_prefix', 'FLHSG'));
        $prefix = strtoupper($prefix);

        return str_starts_with($prefix, $org.'-') ? $prefix : $org.'-'.$prefix;
    }

    public function format(string $prefix, int $year, int $number, int $width = 3): string
    {
        return $this->withOrganisationCode($prefix).'-'.$year.'-'.str_pad((string) $number, $width, '0', STR_PAD_LEFT);
    }
}
