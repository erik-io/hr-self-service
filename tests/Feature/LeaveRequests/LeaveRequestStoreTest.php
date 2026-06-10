<?php

declare(strict_types=1);

namespace Tests\Feature\LeaveRequests;

use App\Models\AbsenceType;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Leave\LeaveDurationCalculatorInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestStoreTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private AbsenceType $vacationType;

    private AbsenceType $sickLeaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::factory()->create([
            'hire_date' => now()->subYears(2)->toDateString(),
            'weekly_working_days' => 5,
            'has_severe_disability' => false,
        ]);
        $this->employee->assignRole('employee');

        $this->vacationType = AbsenceType::where('name', 'Vacation')->first();
        $this->sickLeaveType = AbsenceType::where('name', 'Sick Leave')->first();
    }

    // US1 AC1: Start date, end date and absence type are required fields

    public function test_store_requires_absence_type_id(): void
    {
        $response = $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(12)->toDateString(),
            ]);

        $response->assertSessionHasErrors(['absence_type_id']);
    }

    public function test_store_requires_start_date(): void
    {
        $response = $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'absence_type_id' => $this->vacationType->id,
                'end_date' => now()->addDays(12)->toDateString(),
            ]);

        $response->assertSessionHasErrors(['start_date']);
    }

    public function test_store_requires_end_date(): void
    {
        $response = $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'absence_type_id' => $this->vacationType->id,
                'start_date' => now()->addDays(10)->toDateString(),
            ]);

        $response->assertSessionHasErrors(['end_date']);
    }

    // US5 AC3: End date before start date is rejected

    public function test_store_rejects_end_date_before_start_date(): void
    {
        $response = $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'absence_type_id' => $this->vacationType->id,
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(8)->toDateString(),
            ]);

        $response->assertSessionHasErrors(['end_date']);
    }

    public function test_store_rejects_end_date_before_start_date_without_saving(): void
    {
        $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'absence_type_id' => $this->vacationType->id,
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(8)->toDateString(),
            ]);

        $this->assertDatabaseEmpty('leave_requests');
    }

    // US1 AC2: Sick leave > 3 days triggers AU certificate warning

    public function test_sick_leave_exceeding_three_days_shows_au_certificate_warning(): void
    {
        $this->mock(LeaveDurationCalculatorInterface::class)
            ->shouldReceive('calculateNetDays')
            ->andReturn(4);

        $response = $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'absence_type_id' => $this->sickLeaveType->id,
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(14)->toDateString(),
            ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('warning');
    }

    public function test_sick_leave_of_exactly_three_days_does_not_show_au_certificate_warning(): void
    {
        $this->mock(LeaveDurationCalculatorInterface::class)
            ->shouldReceive('calculateNetDays')
            ->andReturn(3);

        $response = $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'absence_type_id' => $this->sickLeaveType->id,
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(12)->toDateString(),
            ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionMissing('warning');
    }

    // US1 AC3: Overlapping vacation request is prevented

    public function test_overlapping_vacation_request_is_rejected_with_error(): void
    {
        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(25)->toDateString(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'absence_type_id' => $this->vacationType->id,
                'start_date' => now()->addDays(22)->toDateString(),
                'end_date' => now()->addDays(27)->toDateString(),
            ]);

        $response->assertRedirect(route('leave-requests.create'));
        $response->assertSessionHasErrors(['start_date']);
    }

    public function test_overlapping_vacation_request_is_not_persisted_to_database(): void
    {
        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(25)->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'absence_type_id' => $this->vacationType->id,
                'start_date' => now()->addDays(22)->toDateString(),
                'end_date' => now()->addDays(27)->toDateString(),
            ]);

        $this->assertDatabaseCount('leave_requests', 1);
    }

    public function test_approved_existing_vacation_also_prevents_overlapping_new_vacation(): void
    {
        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(25)->toDateString(),
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'absence_type_id' => $this->vacationType->id,
                'start_date' => now()->addDays(22)->toDateString(),
                'end_date' => now()->addDays(27)->toDateString(),
            ]);

        $response->assertSessionHasErrors(['start_date']);
    }

    // Overlap warning flash messages

    public function test_vacation_overlapping_non_vacation_absence_shows_warning_and_proceeds(): void
    {
        // Sick leave already exists for that period
        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->sickLeaveType->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(25)->toDateString(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'absence_type_id' => $this->vacationType->id,
                'start_date' => now()->addDays(22)->toDateString(),
                'end_date' => now()->addDays(27)->toDateString(),
            ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('warning');
        $this->assertDatabaseCount('leave_requests', 2);
    }

    public function test_non_vacation_overlapping_any_request_shows_warning_and_proceeds(): void
    {
        // Vacation already exists for that period
        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(25)->toDateString(),
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'absence_type_id' => $this->sickLeaveType->id,
                'start_date' => now()->addDays(22)->toDateString(),
                'end_date' => now()->addDays(27)->toDateString(),
            ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('warning');
        $this->assertDatabaseCount('leave_requests', 2);
    }
}
