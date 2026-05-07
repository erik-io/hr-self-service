<?php

declare(strict_types=1);

namespace App\Services\Leave\State;

use App\Models\LeaveRequest;
use DomainException;

abstract class BaseLeaveRequestState implements LeaveRequestStateInterface
{
    /**
     * {@inheritDoc}
     */
    public function approve(LeaveRequest $leaveRequest, int $reviewerId): void
    {
        throw new DomainException('This leave request cannot be approved in its current state.');
    }

    /**
     * {@inheritDoc}
     */
    public function reject(LeaveRequest $leaveRequest, int $reviewerId, string $reason): void
    {
        throw new DomainException('This leave request cannot be rejected in its current state.');
    }
}
