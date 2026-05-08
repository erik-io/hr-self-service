<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('employee');
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->hasRole('employee') && $leaveRequest->user_id === $user->id;
    }
}

