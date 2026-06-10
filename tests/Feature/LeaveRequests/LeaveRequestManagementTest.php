<?php

declare(strict_types=1);

namespace Tests\Feature\LeaveRequests;

use App\Models\AbsenceType;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $employee;

    private AbsenceType $vacationType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('supervisor');

        $this->employee = User::factory()->create([
            'hire_date' => now()->subYears(2)->toDateString(),
        ]);
        $this->employee->assignRole('employee');

        $this->vacationType = AbsenceType::where('name', 'Vacation')->first();
    }

    // US3 AC1: Supervisor sees all pending requests

    public function test_supervisor_can_access_management_index(): void
    {
        $response = $this->actingAs($this->supervisor)
            ->get(route('leave-requests.management.index'));

        $response->assertOk();
    }

    public function test_management_index_shows_pending_requests(): void
    {
        $pending = $this->createPendingLeaveRequest();

        $response = $this->actingAs($this->supervisor)
            ->get(route('leave-requests.management.index'));

        $response->assertOk();
        $response->assertViewHas('leaveRequests', fn ($requests) => $requests->contains($pending));
    }

    public function test_management_index_shows_only_pending_requests(): void
    {
        $employee2 = User::factory()->create(['hire_date' => now()->subYears(1)->toDateString()]);
        $employee2->assignRole('employee');

        $pending = LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'status' => 'pending',
        ]);

        $approved = LeaveRequest::create([
            'user_id' => $employee2->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->get(route('leave-requests.management.index'));

        $response->assertViewHas('leaveRequests', function ($requests) use ($pending, $approved) {
            return $requests->contains($pending) && !$requests->contains($approved);
        });
    }

    public function test_employee_cannot_access_management_index(): void
    {
        $response = $this->actingAs($this->employee)
            ->get(route('leave-requests.management.index'));

        $response->assertForbidden();
    }

    // US3 AC2 & AC3: Rejecting requires a non-empty reason

    public function test_reject_without_reason_fails_validation(): void
    {
        $leaveRequest = $this->createPendingLeaveRequest();

        $response = $this->actingAs($this->supervisor)
            ->from(route('leave-requests.management.show', $leaveRequest))
            ->patch(route('leave-requests.management.reject', $leaveRequest), []);

        $response->assertSessionHasErrors(['rejection_reason']);
    }

    public function test_reject_with_empty_reason_fails_validation(): void
    {
        $leaveRequest = $this->createPendingLeaveRequest();

        $response = $this->actingAs($this->supervisor)
            ->from(route('leave-requests.management.show', $leaveRequest))
            ->patch(route('leave-requests.management.reject', $leaveRequest), [
                'rejection_reason' => '',
            ]);

        $response->assertSessionHasErrors(['rejection_reason']);
    }

    public function test_reject_with_too_short_reason_fails_validation(): void
    {
        $leaveRequest = $this->createPendingLeaveRequest();

        $response = $this->actingAs($this->supervisor)
            ->from(route('leave-requests.management.show', $leaveRequest))
            ->patch(route('leave-requests.management.reject', $leaveRequest), [
                'rejection_reason' => 'No', // less than 5 characters
            ]);

        $response->assertSessionHasErrors(['rejection_reason']);
    }

    public function test_leave_request_remains_pending_when_rejection_reason_is_missing(): void
    {
        $leaveRequest = $this->createPendingLeaveRequest();

        $this->actingAs($this->supervisor)
            ->from(route('leave-requests.management.show', $leaveRequest))
            ->patch(route('leave-requests.management.reject', $leaveRequest), []);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'pending',
        ]);
    }

    public function test_reject_with_valid_reason_updates_status_to_rejected(): void
    {
        $leaveRequest = $this->createPendingLeaveRequest();
        $reason = 'Not enough staff coverage during this period.';

        $this->actingAs($this->supervisor)
            ->from(route('leave-requests.management.show', $leaveRequest))
            ->patch(route('leave-requests.management.reject', $leaveRequest), [
                'rejection_reason' => $reason,
            ]);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewer_id' => $this->supervisor->id,
        ]);
    }

    public function test_reject_with_valid_reason_redirects_to_management_index(): void
    {
        $leaveRequest = $this->createPendingLeaveRequest();

        $response = $this->actingAs($this->supervisor)
            ->from(route('leave-requests.management.show', $leaveRequest))
            ->patch(route('leave-requests.management.reject', $leaveRequest), [
                'rejection_reason' => 'Insufficient staffing during this period.',
            ]);

        $response->assertRedirect(route('leave-requests.management.index'));
    }

    // US3 AC1: Supervisor can approve a request

    public function test_approve_updates_status_to_approved(): void
    {
        $leaveRequest = $this->createPendingLeaveRequest();

        $this->actingAs($this->supervisor)
            ->patch(route('leave-requests.management.approve', $leaveRequest));

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
            'reviewer_id' => $this->supervisor->id,
        ]);
    }

    public function test_approve_redirects_to_management_index(): void
    {
        $leaveRequest = $this->createPendingLeaveRequest();

        $response = $this->actingAs($this->supervisor)
            ->patch(route('leave-requests.management.approve', $leaveRequest));

        $response->assertRedirect(route('leave-requests.management.index'));
    }

    private function createPendingLeaveRequest(): LeaveRequest
    {
        return LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'status' => 'pending',
        ]);
    }
}
