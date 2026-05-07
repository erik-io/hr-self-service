<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\LeaveRequest;
use DomainException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

interface LeaveRequestServiceInterface
{
    /**
     * Approves the given leave request.
     *
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    public function approve(LeaveRequest $leaveRequest, int $reviewerId): void;

    /**
     * Rejects the given leave request with a specific reason.
     *
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    public function reject(LeaveRequest $leaveRequest, int $reviewerId, string $reason): void;

    /**
     * Checks if a proposed date range overlaps with existing non-rejected requests for a user.
     */
    public function hasOverlappingRequests(int $userId, Carbon $startDate, Carbon $endDate): bool;
}
