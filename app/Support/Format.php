<?php

namespace App\Support;

use App\Enums\AgentRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\SailingStatus;
use DateTimeInterface;

/**
 * Display helpers for Blade (admin panel). Money matches SC.money() in core.js: "$2,310".
 */
final class Format
{
    public static function money(float|int|string|null $amount, int $decimals = 0): string
    {
        $amount = (float) $amount;

        return ($amount < 0 ? '−' : '').config('cruise.currency_symbol').number_format(abs($amount), $decimals);
    }

    public static function date(?DateTimeInterface $date, string $format = 'j M Y'): string
    {
        return $date ? $date->format($format) : '—';
    }

    /**
     * [tag class, label] for a status badge.
     */
    public static function badge(BookingStatus|PaymentStatus|SailingStatus|AgentRequestStatus $status): array
    {
        return match ($status) {
            AgentRequestStatus::Pending => ['tag-sun', 'Pending'],
            AgentRequestStatus::Reviewing => ['tag-ocean', 'Under review'],
            AgentRequestStatus::Accepted => ['tag-lagoon', 'Accepted'],
            AgentRequestStatus::Rejected => ['bg-[#FDF0F0] text-[#8E1F1F]', 'Rejected'],
            BookingStatus::Confirmed => ['tag-lagoon', 'Confirmed'],
            BookingStatus::Cancelled => ['tag-sun', 'Cancelled'],
            PaymentStatus::Unpaid => ['tag-sun', 'Unpaid'],
            PaymentStatus::Partial => ['tag-sun', 'Part paid'],
            PaymentStatus::Paid => ['tag-lagoon', 'Paid'],
            PaymentStatus::Refunded => ['tag-ocean', 'Refunded'],
            SailingStatus::Open => ['tag-lagoon', 'Open'],
            SailingStatus::Closed => ['tag-ocean', 'Closed'],
            SailingStatus::Cancelled => ['tag-sun', 'Cancelled'],
        };
    }

    public static function guests(int $adults, int $children, int $infants): string
    {
        return collect([
            $adults ? $adults.' '.($adults === 1 ? 'adult' : 'adults') : null,
            $children ? $children.' '.($children === 1 ? 'child' : 'children') : null,
            $infants ? $infants.' '.($infants === 1 ? 'infant' : 'infants') : null,
        ])->filter()->join(' + ');
    }
}
