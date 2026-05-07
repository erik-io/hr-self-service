<?php

declare(strict_types=1);

namespace App\Services\Leave\State;

use App\Models\LeaveRequest;
use InvalidArgumentException;

class PendingState extends BaseLeaveRequestState
{
    /**
     * Approves the leave request.
     *
     * @throws InvalidArgumentException
     */
    public function approve(LeaveRequest $leaveRequest, int $reviewerId): void
    {
        if ($reviewerId <= 0) {
            throw new InvalidArgumentException('Invalid reviewer ID');
        }

        $leaveRequest->update([
            'status' => 'approved',
            'reviewer_id' => $reviewerId,
        ]);
    }

    /**
     * Rejects the leave request with a mandatory reason.
     *
     * @throws InvalidArgumentException
     */
    public function reject(LeaveRequest $leaveRequest, int $reviewerId, string $reason): void
    {
        if ($reviewerId <= 0) {
            throw new InvalidArgumentException('Invalid reviewer ID');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Approval reason cannot be empty');
        }

        $leaveRequest->update([
            'status' => 'rejected',
            'reviewer_id' => $reviewerId,
            'rejection_reason' => $reason,
        ]);
    }
}
