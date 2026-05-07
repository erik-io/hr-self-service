<?php

declare(strict_types=1);

namespace App\Services\Leave\State;

use App\Models\LeaveRequest;

interface LeaveRequestStateInterface
{
    /**
     * @throws \DomainException
     */
    public function approve(LeaveRequest $leaveRequest, int $reviewerId): void;

    /**
     * @throws \DomainException
     */
    public function reject(LeaveRequest $leaveRequest, int $reviewerId, string $reason): void;
}
