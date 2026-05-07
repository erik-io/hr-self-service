<?php

declare(strict_types=1);

namespace App\Services\Leave;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

interface LeaveDurationCalculatorInterface
{
    /**
     * Calculates the net amount of leave days between two dates.
     *
     * @throws InvalidArgumentException
     */
    public function calculateNetDays(Carbon $startDate, Carbon $endDate): int;
}
