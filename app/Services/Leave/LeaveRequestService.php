<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\LeaveRequest;
use App\Services\Leave\State\LeaveRequestStateFactory;
use Illuminate\Database\Eloquent\Collection;
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
    public function getOverlappingRequests(int $userId, Carbon $startDate, Carbon $endDate): Collection
    {
        return LeaveRequest::with('absenceType')
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function getTeamOverlappingRequests(Carbon $startDate, Carbon $endDate, int $excludeUserId): Collection
    {
        return LeaveRequest::with(['user', 'absenceType'])
            ->where('user_id', '!=', $excludeUserId)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->get();
    }
}
