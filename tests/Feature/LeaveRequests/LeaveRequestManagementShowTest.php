<?php

declare(strict_types=1);

namespace Tests\Feature\LeaveRequests;

use App\Models\AbsenceType;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestManagementShowTest extends TestCase
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

    // History route

    public function test_supervisor_can_access_management_history(): void
    {
        $response = $this->actingAs($this->supervisor)
            ->get(route('leave-requests.management.history'));

        $response->assertOk();
    }

    public function test_history_shows_all_requests_not_just_pending(): void
    {
        $pending = $this->createLeaveRequest('pending');
        $approved = $this->createLeaveRequest('approved');
        $rejected = $this->createLeaveRequest('rejected');

        $response = $this->actingAs($this->supervisor)
            ->get(route('leave-requests.management.history'));

        $response->assertViewHas('leaveRequests', function ($requests) use ($pending, $approved, $rejected) {
            return $requests->contains($pending)
                && $requests->contains($approved)
                && $requests->contains($rejected);
        });
    }

    public function test_history_filters_by_status(): void
    {
        $pending = $this->createLeaveRequest('pending');
        $approved = $this->createLeaveRequest('approved');

        $response = $this->actingAs($this->supervisor)
            ->get(route('leave-requests.management.history', ['status' => 'approved']));

        $response->assertViewHas('leaveRequests', function ($requests) use ($pending, $approved) {
            return $requests->contains($approved) && !$requests->contains($pending);
        });
    }

    public function test_history_invalid_status_filter_returns_all_requests(): void
    {
        $pending = $this->createLeaveRequest('pending');
        $approved = $this->createLeaveRequest('approved');

        $response = $this->actingAs($this->supervisor)
            ->get(route('leave-requests.management.history', ['status' => 'invalid']));

        $response->assertViewHas('leaveRequests', function ($requests) use ($pending, $approved) {
            return $requests->contains($pending) && $requests->contains($approved);
        });
    }

    public function test_history_default_sort_is_created_at_desc(): void
    {
        $first = $this->createLeaveRequest('approved');
        $first->forceFill(['created_at' => now()->subMinutes(5)])->save();

        $second = $this->createLeaveRequest('pending');

        $response = $this->actingAs($this->supervisor)
            ->get(route('leave-requests.management.history'));

        $response->assertViewHas('leaveRequests', function ($requests) use ($first, $second) {
            return $requests->first()->id === $second->id;
        });
    }

    public function test_employee_cannot_access_management_history(): void
    {
        $response = $this->actingAs($this->employee)
            ->get(route('leave-requests.management.history'));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_from_history(): void
    {
        $response = $this->get(route('leave-requests.management.history'));

        $response->assertRedirect(route('login'));
    }

    public function test_history_passes_pending_count_to_view(): void
    {
        $this->createLeaveRequest('pending');
        $this->createLeaveRequest('pending');
        $this->createLeaveRequest('approved');

        $response = $this->actingAs($this->supervisor)
            ->get(route('leave-requests.management.history'));

        $response->assertViewHas('pendingCount', 2);
    }

    public function test_history_invalid_query_params_fall_back_to_defaults(): void
    {
        $response = $this->actingAs($this->supervisor)
            ->get(route('leave-requests.management.history', [
                'sort_by' => 'invalid_column',
                'sort_direction' => 'sideways',
                'per_page' => 999,
            ]));

        $response->assertOk();
    }

    // Show route

    public function test_supervisor_can_access_management_show(): void
    {
        $leaveRequest = $this->createLeaveRequest();

        $response = $this->actingAs($this->supervisor)
            ->get(route('leave-requests.management.show', $leaveRequest));

        $response->assertOk();
    }

    public function test_show_passes_leave_request_to_view(): void
    {
        $leaveRequest = $this->createLeaveRequest();

        $response = $this->actingAs($this->supervisor)
            ->get(route('leave-requests.management.show', $leaveRequest));

        $response->assertViewHas('leaveRequest', fn ($lr) => $lr->id === $leaveRequest->id);
    }

    public function test_show_passes_team_overlaps_to_view(): void
    {
        $leaveRequest = $this->createLeaveRequest();

        $response = $this->actingAs($this->supervisor)
            ->get(route('leave-requests.management.show', $leaveRequest));

        $response->assertViewHas('teamOverlaps');
    }

    public function test_show_team_overlaps_includes_overlapping_requests_from_other_employees(): void
    {
        $otherEmployee = User::factory()->create(['hire_date' => now()->subYear()->toDateString()]);
        $otherEmployee->assignRole('employee');

        $leaveRequest = $this->createLeaveRequest('pending', $this->employee);

        $overlapping = LeaveRequest::create([
            'user_id' => $otherEmployee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-05',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->get(route('leave-requests.management.show', $leaveRequest));

        $response->assertViewHas('teamOverlaps', fn ($overlaps) => $overlaps->contains($overlapping));
    }

    public function test_show_team_overlaps_excludes_own_requests(): void
    {
        $leaveRequest = $this->createLeaveRequest();

        $ownOther = LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-05',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->get(route('leave-requests.management.show', $leaveRequest));

        $response->assertViewHas('teamOverlaps', fn ($overlaps) => !$overlaps->contains($ownOther));
    }

    public function test_employee_cannot_access_management_show(): void
    {
        $leaveRequest = $this->createLeaveRequest();

        $response = $this->actingAs($this->employee)
            ->get(route('leave-requests.management.show', $leaveRequest));

        $response->assertForbidden();
    }

    public function test_employee_cannot_access_approve_route(): void
    {
        $leaveRequest = $this->createLeaveRequest();

        $response = $this->actingAs($this->employee)
            ->patch(route('leave-requests.management.approve', $leaveRequest));

        $response->assertForbidden();
    }

    public function test_employee_cannot_access_reject_route(): void
    {
        $leaveRequest = $this->createLeaveRequest();

        $response = $this->actingAs($this->employee)
            ->patch(route('leave-requests.management.reject', $leaveRequest), [
                'rejection_reason' => 'Some reason here.',
            ]);

        $response->assertForbidden();
    }

    // Flash messages

    public function test_approve_sets_success_flash_message(): void
    {
        $leaveRequest = $this->createLeaveRequest();

        $response = $this->actingAs($this->supervisor)
            ->patch(route('leave-requests.management.approve', $leaveRequest));

        $response->assertSessionHas('success');
    }

    public function test_reject_sets_success_flash_message(): void
    {
        $leaveRequest = $this->createLeaveRequest();

        $response = $this->actingAs($this->supervisor)
            ->patch(route('leave-requests.management.reject', $leaveRequest), [
                'rejection_reason' => 'Insufficient staffing during this period.',
            ]);

        $response->assertSessionHas('success');
    }

    private function createLeaveRequest(string $status = 'pending', ?User $user = null): LeaveRequest
    {
        return LeaveRequest::create([
            'user_id' => ($user ?? $this->employee)->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-06',
            'status' => $status,
        ]);
    }
}
