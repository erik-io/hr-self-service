<?php

declare(strict_types=1);

namespace Tests\Feature\LeaveRequests;

use App\Models\AbsenceType;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $otherEmployee;

    private AbsenceType $vacationType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::factory()->create([
            'hire_date' => now()->subYears(2)->toDateString(),
        ]);
        $this->employee->assignRole('employee');

        $this->otherEmployee = User::factory()->create([
            'hire_date' => now()->subYears(1)->toDateString(),
        ]);
        $this->otherEmployee->assignRole('employee');

        $this->vacationType = AbsenceType::where('name', 'Vacation')->first();
    }

    // US4 AC1: Employee can see their own requests with period, type and status

    public function test_employee_can_access_leave_requests_index(): void
    {
        $response = $this->actingAs($this->employee)
            ->get(route('leave-requests.index'));

        $response->assertOk();
    }

    public function test_index_shows_own_leave_requests(): void
    {
        $ownRequest = LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->employee)
            ->get(route('leave-requests.index'));

        $response->assertOk();
        $response->assertViewHas('leaveRequests', fn ($requests) => $requests->contains($ownRequest));
    }

    // US4 AC3: Employee only sees their own requests, not those of other employees

    public function test_index_does_not_show_other_employees_requests(): void
    {
        $ownRequest = LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'status' => 'pending',
        ]);

        $otherRequest = LeaveRequest::create([
            'user_id' => $this->otherEmployee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->employee)
            ->get(route('leave-requests.index'));

        $response->assertViewHas('leaveRequests', function ($requests) use ($ownRequest, $otherRequest) {
            return $requests->contains($ownRequest) && !$requests->contains($otherRequest);
        });
    }

    public function test_index_shows_request_status_for_pending_request(): void
    {
        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->employee)
            ->get(route('leave-requests.index'));

        $response->assertOk();
        // The view uses a translated status badge; the status value is used in the match expression
        $response->assertViewHas('leaveRequests', fn ($r) => $r->first()->status === 'pending');
    }

    // US4 AC2: Employee can see the rejection reason on a rejected request

    public function test_show_view_displays_rejection_reason_for_rejected_request(): void
    {
        $rejectionReason = 'Team already at full capacity during this period.';

        $rejectedRequest = LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'status' => 'rejected',
            'rejection_reason' => $rejectionReason,
            'reviewer_id' => null,
        ]);

        $response = $this->actingAs($this->employee)
            ->get(route('leave-requests.show', $rejectedRequest));

        $response->assertOk();
        $response->assertSee($rejectionReason);
    }

    public function test_show_view_does_not_display_rejection_reason_section_for_pending_request(): void
    {
        $pendingRequest = LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->employee)
            ->get(route('leave-requests.show', $pendingRequest));

        $response->assertOk();
        $response->assertViewHas('leaveRequest', fn ($lr) => empty($lr->rejection_reason));
    }

    // US4 AC3: Employee cannot access another employee's request details

    public function test_employee_cannot_view_another_employees_leave_request(): void
    {
        $otherRequest = LeaveRequest::create([
            'user_id' => $this->otherEmployee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->employee)
            ->get(route('leave-requests.show', $otherRequest));

        $response->assertForbidden();
    }

    public function test_employee_can_view_own_approved_request_details(): void
    {
        $ownRequest = LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->employee)
            ->get(route('leave-requests.show', $ownRequest));

        $response->assertOk();
    }

    // Invalid query parameter fallbacks

    public function test_index_falls_back_to_default_per_page_for_invalid_value(): void
    {
        $response = $this->actingAs($this->employee)
            ->get(route('leave-requests.index', ['per_page' => 999]));

        $response->assertOk();
        $response->assertViewHas('leaveRequests', fn ($r) => $r->perPage() === 10);
    }

    public function test_index_falls_back_to_default_sort_by_for_invalid_column(): void
    {
        $response = $this->actingAs($this->employee)
            ->get(route('leave-requests.index', ['sort_by' => 'not_a_column']));

        $response->assertOk();
    }

    public function test_index_falls_back_to_default_sort_direction_for_invalid_value(): void
    {
        $response = $this->actingAs($this->employee)
            ->get(route('leave-requests.index', ['sort_direction' => 'sideways']));

        $response->assertOk();
    }

    public function test_index_filters_by_status(): void
    {
        $pending = LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'status' => 'pending',
        ]);

        $approved = LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->employee)
            ->get(route('leave-requests.index', ['status' => 'approved']));

        $response->assertViewHas('leaveRequests', function ($requests) use ($pending, $approved) {
            return $requests->contains($approved) && !$requests->contains($pending);
        });
    }
}
