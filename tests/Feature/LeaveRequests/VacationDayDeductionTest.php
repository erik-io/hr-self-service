<?php

declare(strict_types=1);

namespace Tests\Feature\LeaveRequests;

use App\Models\AbsenceType;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Leave\LeaveDurationCalculatorInterface;
use App\Services\Leave\LeaveEntitlementCalculatorInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacationDayDeductionTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private AbsenceType $vacationType;

    private AbsenceType $sickLeaveType;

    private AbsenceType $parentalLeaveType;

    private AbsenceType $unpaidLeaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::factory()->create([
            'hire_date' => now()->subYears(2)->toDateString(),
            'weekly_working_days' => 5,
            'has_severe_disability' => false,
        ]);
        $this->employee->assignRole('employee');

        // Absence types are created by AbsenceTypeSeeder (runs because $seed = true)
        $this->vacationType = AbsenceType::where('name', 'Vacation')->first();
        $this->sickLeaveType = AbsenceType::where('name', 'Sick Leave')->first();
        $this->parentalLeaveType = AbsenceType::where('name', 'Parental Leave')->first();
        $this->unpaidLeaveType = AbsenceType::where('name', 'Unpaid Leave')->first();
    }

    public function test_approved_vacation_request_reduces_remaining_days(): void
    {
        $before = $this->getRemainingDays();

        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
            'status' => 'approved',
        ]);

        $after = $this->getRemainingDays();

        $this->assertLessThan($before, $after);
    }

    public function test_pending_vacation_request_reduces_remaining_days(): void
    {
        $before = $this->getRemainingDays();

        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
            'status' => 'pending',
        ]);

        $after = $this->getRemainingDays();

        $this->assertLessThan($before, $after);
    }

    public function test_rejected_vacation_request_does_not_reduce_remaining_days(): void
    {
        $before = $this->getRemainingDays();

        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
            'status' => 'rejected',
        ]);

        $after = $this->getRemainingDays();

        $this->assertEquals($before, $after);
    }

    public function test_sick_leave_does_not_reduce_remaining_days(): void
    {
        $before = $this->getRemainingDays();

        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->sickLeaveType->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
            'status' => 'approved',
        ]);

        $after = $this->getRemainingDays();

        $this->assertEquals($before, $after);
    }

    public function test_parental_leave_does_not_reduce_remaining_days(): void
    {
        $before = $this->getRemainingDays();

        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->parentalLeaveType->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
            'status' => 'approved',
        ]);

        $after = $this->getRemainingDays();

        $this->assertEquals($before, $after);
    }

    public function test_unpaid_leave_does_not_reduce_remaining_days(): void
    {
        $before = $this->getRemainingDays();

        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->unpaidLeaveType->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
            'status' => 'approved',
        ]);

        $after = $this->getRemainingDays();

        $this->assertEquals($before, $after);
    }

    public function test_vacation_request_exceeding_entitlement_is_rejected(): void
    {
        $this->mock(LeaveEntitlementCalculatorInterface::class)
            ->shouldReceive('calculateAnnualEntitlement')
            ->andReturn(5);

        $this->mock(LeaveDurationCalculatorInterface::class)
            ->shouldReceive('calculateNetDays')
            ->andReturn(3);

        // Use up 3 of 5 days with an existing vacation request
        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-04',
            'status' => 'approved',
        ]);

        // Try to submit another 3-day vacation: 3 used + 3 new = 6 > 5 → should fail
        $response = $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'absence_type_id' => $this->vacationType->id,
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(12)->toDateString(),
            ]);

        $response->assertRedirect(route('leave-requests.create'));
        $response->assertSessionHasErrors(['end_date']);
    }

    public function test_sick_leave_is_not_subject_to_vacation_day_limit(): void
    {
        $this->mock(LeaveEntitlementCalculatorInterface::class)
            ->shouldReceive('calculateAnnualEntitlement')
            ->andReturn(5);

        $this->mock(LeaveDurationCalculatorInterface::class)
            ->shouldReceive('calculateNetDays')
            ->andReturn(3);

        // Use up all vacation days with an existing approved vacation
        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
            'status' => 'approved',
        ]);

        // Sick leave should still be submittable even though vacation is exhausted
        $response = $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'absence_type_id' => $this->sickLeaveType->id,
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(14)->toDateString(),
            ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->sickLeaveType->id,
            'status' => 'pending',
        ]);
    }

    public function test_parental_leave_is_not_subject_to_vacation_day_limit(): void
    {
        $this->mock(LeaveEntitlementCalculatorInterface::class)
            ->shouldReceive('calculateAnnualEntitlement')
            ->andReturn(5);

        $this->mock(LeaveDurationCalculatorInterface::class)
            ->shouldReceive('calculateNetDays')
            ->andReturn(3);

        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'absence_type_id' => $this->parentalLeaveType->id,
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(14)->toDateString(),
            ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->parentalLeaveType->id,
            'status' => 'pending',
        ]);
    }

    public function test_unpaid_leave_is_not_subject_to_vacation_day_limit(): void
    {
        $this->mock(LeaveEntitlementCalculatorInterface::class)
            ->shouldReceive('calculateAnnualEntitlement')
            ->andReturn(5);

        $this->mock(LeaveDurationCalculatorInterface::class)
            ->shouldReceive('calculateNetDays')
            ->andReturn(3);

        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->employee)
            ->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), [
                'absence_type_id' => $this->unpaidLeaveType->id,
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(14)->toDateString(),
            ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->unpaidLeaveType->id,
            'status' => 'pending',
        ]);
    }

    private function getRemainingDays(): int
    {
        return $this->actingAs($this->employee)
            ->get(route('leave-requests.create'))
            ->assertOk()
            ->viewData('remainingDays');
    }
}
