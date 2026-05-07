<?php

declare(strict_types=1);

namespace App\Services\Leave\State;

use http\Exception\InvalidArgumentException;

class LeaveRequestStateFactory
{
    public static function make(string $status): LeaveRequestStateInterface
    {
        return match ($status) {
            'pending' => new PendingState,
            'approved' => new ApprovedState,
            'rejected' => new RejectedState,
            default => throw new InvalidArgumentException("Invalid state: $status"),
        };
    }
}
