<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\LeaveRequest;
use App\Services\Leave\State\LeaveRequestStateFactory;
use Illuminate\Support\Carbon;

class LeaveRequestService implements LeaveRequestServiceInterface
{
    /**
     * {@inheritDoc}
     */
    public function approve(LeaveRequest $leaveRequest, int $reviewerId): void
    {
        $state = LeaveRequestStateFactory::make($leaveRequest->status);
        $state->approve($leaveRequest, $reviewerId);
    }

    /**
     * {@inheritDoc}
     */
    public function reject(LeaveRequest $leaveRequest, int $reviewerId, string $reason): void
    {
        $state = LeaveRequestStateFactory::make($leaveRequest->status);
        $state->reject($leaveRequest, $reviewerId, $reason);
    }

    /**
     * {@inheritDoc}
     */
    public function hasOverlappingRequests(int $userId, Carbon $startDate, Carbon $endDate): bool
    {
        return LeaveRequest::where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->exists();
    }
}
