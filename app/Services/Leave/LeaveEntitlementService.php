<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\User;
use InvalidArgumentException;

class LeaveEntitlementService implements LeaveEntitlementCalculatorInterface
{
    private const BASE_LEAVE_DAYS_FOR_FULL_TIME = 30;
    private const STANDARD_WEEKLY_WORKING_DAYS = 5;
    private const DISABILITY_BONUS_DAYS = 5;
    private const LOYALTY_BONUS_YEARS_INTERVAL = 5;
    private const MAX_LOYALTY_BONUS_DAYS = 5;

    /**
     * {@inheritDoc}
     */
    public function calculateAnnualEntitlement(User $user, int $year): int
    {
        if ($user->hire_date === null) {
            throw new InvalidArgumentException('User must have a hire date to calculate leave entitlement.');
        }

        $baseEntitlement = $this->calculateBaseEntitlement($user->weekly_working_days);
        $disabilityBonus = $this->calculateDisabilityBonus($user);
        $loyaltyBonus = $this->calculateLoyaltyBonus($user, $year);

        return (int) round($baseEntitlement + $disabilityBonus + $loyaltyBonus);
    }

    /**
     * Calculates the base entitlement proportionally to the weekly working days.
     */
    private function calculateBaseEntitlement(int $weekly_working_days): float
    {
        if ($weekly_working_days <= 0 || $weekly_working_days > 7) {
            throw new InvalidArgumentException('Weekly working days must be between 1 and 7');
        }

        return (self::BASE_LEAVE_DAYS_FOR_FULL_TIME / self::STANDARD_WEEKLY_WORKING_DAYS) * $weekly_working_days;
    }

    /**
     * Calculates additional leave days for severe disability proportionally.
     */
    private function calculateDisabilityBonus(User $user): float
    {
        if (! $user->has_severe_disability) {
            return 0;
        }

        return (self::DISABILITY_BONUS_DAYS / self::STANDARD_WEEKLY_WORKING_DAYS) * $user->weekly_working_days;
    }

    /**
     * Calculates loyalty bonus das based on years of service.
     */
    private function calculateLoyaltyBonus(User $user, int $year): int
    {
        $yearsOfService = $year - $user->hire_date->year;

        if ($yearsOfService < self::LOYALTY_BONUS_YEARS_INTERVAL) {
            return 0;
        }

        $bonusDays = (int) floor($yearsOfService / self::LOYALTY_BONUS_YEARS_INTERVAL);

        return min($bonusDays, self::MAX_LOYALTY_BONUS_DAYS);
    }
}
