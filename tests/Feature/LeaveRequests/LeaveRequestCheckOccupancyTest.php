<?php

declare(strict_types=1);

namespace Tests\Feature\LeaveRequests;

use App\Models\AbsenceType;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestCheckOccupancyTest extends TestCase
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
        ]);
        $this->employee->assignRole('employee');

        $this->vacationType = AbsenceType::where('name', 'Vacation')->first();
        $this->sickLeaveType = AbsenceType::where('name', 'Sick Leave')->first();
    }

    // Validation

    public function test_requires_start_date(): void
    {
        $response = $this->checkOccupancy(['end_date' => '2026-08-10']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['start_date']);
    }

    public function test_requires_end_date(): void
    {
        $response = $this->checkOccupancy(['start_date' => '2026-08-04']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['end_date']);
    }

    public function test_end_date_must_be_on_or_after_start_date(): void
    {
        $response = $this->checkOccupancy([
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-05',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['end_date']);
    }

    // Personal overlap

    public function test_personal_overlap_returns_zero_max_absences_and_red_status(): void
    {
        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-07',
            'status' => 'pending',
        ]);

        $response = $this->checkOccupancy([
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-06',
        ]);

        $response->assertOk();
        $response->assertJson(['max_absences' => 0]);
        $response->assertJsonPath('status.color', 'bg-red-500');
    }

    public function test_personal_overlap_with_vacation_includes_vacation_overlap_text(): void
    {
        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-07',
            'status' => 'approved',
        ]);

        $response = $this->checkOccupancy([
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-06',
        ]);

        $response->assertOk();
        $response->assertJsonPath('max_absences', 0);
    }

    public function test_personal_overlap_with_non_vacation_includes_absence_overlap_text(): void
    {
        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'absence_type_id' => $this->sickLeaveType->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-07',
            'status' => 'pending',
        ]);

        $response = $this->checkOccupancy([
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-06',
        ]);

        $response->assertOk();
        $response->assertJsonPath('max_absences', 0);
        $response->assertJsonPath('status.color', 'bg-red-500');
    }

    // No personal overlap — team occupancy

    public function test_no_overlap_returns_max_absences_count(): void
    {
        $other = User::factory()->create(['hire_date' => now()->subYear()->toDateString()]);
        $other->assignRole('employee');

        LeaveRequest::create([
            'user_id' => $other->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-06',
            'status' => 'approved',
        ]);

        $response = $this->checkOccupancy([
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-06',
        ]);

        $response->assertOk();
        $response->assertJson(['max_absences' => 1]);
    }

    public function test_no_requests_returns_zero_max_absences_with_green_status(): void
    {
        $response = $this->checkOccupancy([
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-06',
        ]);

        $response->assertOk();
        $response->assertJson(['max_absences' => 0]);
        $response->assertJsonPath('status.color', 'bg-green-500');
    }

    // getOccupancyStatus colour thresholds

    public function test_one_concurrent_absence_returns_yellow_caution_status(): void
    {
        $other = User::factory()->create(['hire_date' => now()->subYear()->toDateString()]);
        $other->assignRole('employee');

        LeaveRequest::create([
            'user_id' => $other->id,
            'absence_type_id' => $this->vacationType->id,
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-06',
            'status' => 'approved',
        ]);

        $response = $this->checkOccupancy([
            'start_date' => '2026-08-05',
            'end_date' => '2026-08-05',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status.color', 'bg-yellow-400');
    }

    public function test_three_or_more_concurrent_absences_returns_red_warning_status(): void
    {
        foreach (range(1, 3) as $i) {
            $user = User::factory()->create(['hire_date' => now()->subYear()->toDateString()]);
            $user->assignRole('employee');

            LeaveRequest::create([
                'user_id' => $user->id,
                'absence_type_id' => $this->vacationType->id,
                'start_date' => '2026-08-04',
                'end_date' => '2026-08-06',
                'status' => 'approved',
            ]);
        }

        $response = $this->checkOccupancy([
            'start_date' => '2026-08-05',
            'end_date' => '2026-08-05',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status.color', 'bg-red-500');
        $response->assertJson(['max_absences' => 3]);
    }

    public function test_guest_cannot_access_check_occupancy(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->get(route('leave-requests.check-occupancy', [
                'start_date' => '2026-08-04',
                'end_date' => '2026-08-06',
            ]));

        $response->assertUnauthorized();
    }

    private function checkOccupancy(array $params = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->employee)
            ->withHeaders(['Accept' => 'application/json'])
            ->get(route('leave-requests.check-occupancy', $params));
    }
}
