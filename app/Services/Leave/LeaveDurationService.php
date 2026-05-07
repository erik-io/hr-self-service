<?php

declare(strict_types=1);

namespace App\Services\Leave;

use http\Exception\InvalidArgumentException;
use Illuminate\Support\Carbon;
use Spatie\Holidays\Holidays;

class LeaveDurationService implements LeaveDurationCalculatorInterface
{
    /**
     * {@inheritDoc}
     */
    public function calculateNetDays(Carbon $startDate, Carbon $endDate): int
    {
        if ($endDate->isBefore($startDate)) {
            throw new InvalidArgumentException('End date must be after start date.');
        }

        $netDays = 0;
        $currentDate = $startDate->copy();
        $holidays = Holidays::for('de');

        while ($currentDate->lte($endDate)) {
            if (! $currentDate->isWeekend() && ! $holidays->isHoliday($currentDate)) {
                $netDays++;
            }

            $currentDate->addDay();
        }

        return $netDays;
    }
}
