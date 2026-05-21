<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsenceType extends Model
{
    protected $fillable = [
        'name',
        'deducts_vacation_days',
        'requires_approval',
    ];

    public function leaveRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
