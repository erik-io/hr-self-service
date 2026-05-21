<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\User;

interface LeaveEntitlementCalculatorInterface
{
    /**
     * Calculate the total annual leave entitlement for a specific employee.
     *
     * @param  User  $user  The user for whom to calculate the entitlement.
     * @param  int  $year  The year for which the entitlement is calculated.
     * @throws \InvalidArgumentException If the user has missing required data.
     * @return int The total number of leave days the user is entitled to.
     */
    public function calculateAnnualEntitlement(User $user, int $year): int;
}
