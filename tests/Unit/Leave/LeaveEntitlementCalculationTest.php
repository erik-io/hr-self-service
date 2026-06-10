<?php

declare(strict_types=1);

namespace Tests\Unit\Leave;

use App\Models\User;
use App\Services\Leave\LeaveEntitlementService;
use Tests\TestCase;

class LeaveEntitlementCalculationTest extends TestCase
{
    private LeaveEntitlementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LeaveEntitlementService();
    }

    // US2 AC2: Entitlement = base entitlement + loyalty bonus + disability bonus

    public function test_full_time_employee_receives_30_base_days(): void
    {
        $user = $this->makeUser('2024-01-01', weeklyWorkingDays: 5);

        $entitlement = $this->service->calculateAnnualEntitlement($user, 2026);

        $this->assertEquals(30, $entitlement);
    }

    public function test_part_time_employee_receives_proportional_base_days(): void
    {
        $user = $this->makeUser('2024-01-01', weeklyWorkingDays: 4);

        $entitlement = $this->service->calculateAnnualEntitlement($user, 2026);

        $this->assertEquals(24, $entitlement);
    }

    public function test_three_day_week_employee_receives_proportional_base_days(): void
    {
        $user = $this->makeUser('2024-01-01', weeklyWorkingDays: 3);

        $entitlement = $this->service->calculateAnnualEntitlement($user, 2026);

        $this->assertEquals(18, $entitlement);
    }

    public function test_severe_disability_adds_proportional_bonus_days_for_full_time(): void
    {
        $withoutDisability = $this->makeUser('2024-01-01', weeklyWorkingDays: 5, hasSevereDisability: false);
        $withDisability = $this->makeUser('2024-01-01', weeklyWorkingDays: 5, hasSevereDisability: true);

        $without = $this->service->calculateAnnualEntitlement($withoutDisability, 2026);
        $with = $this->service->calculateAnnualEntitlement($withDisability, 2026);

        $this->assertEquals(5, $with - $without);
    }

    public function test_severe_disability_bonus_is_proportional_to_working_days(): void
    {
        $user = $this->makeUser('2024-01-01', weeklyWorkingDays: 4, hasSevereDisability: true);

        $entitlement = $this->service->calculateAnnualEntitlement($user, 2026);

        // Base: (30/5)*4 = 24, Disability: (5/5)*4 = 4, total: 28
        $this->assertEquals(28, $entitlement);
    }

    public function test_no_loyalty_bonus_before_5_years_of_service(): void
    {
        $user = $this->makeUser('2022-01-01', weeklyWorkingDays: 5);

        $entitlement = $this->service->calculateAnnualEntitlement($user, 2026);

        $this->assertEquals(30, $entitlement); // No loyalty bonus (4 years of service)
    }

    public function test_loyalty_bonus_of_1_day_awarded_at_exactly_5_years(): void
    {
        $user = $this->makeUser('2021-01-01', weeklyWorkingDays: 5);

        $entitlement = $this->service->calculateAnnualEntitlement($user, 2026);

        $this->assertEquals(31, $entitlement); // 30 base + 1 loyalty bonus
    }

    public function test_loyalty_bonus_increases_by_1_day_every_5_years(): void
    {
        $at5Years = $this->makeUser('2021-01-01', weeklyWorkingDays: 5);
        $at10Years = $this->makeUser('2016-01-01', weeklyWorkingDays: 5);
        $at15Years = $this->makeUser('2011-01-01', weeklyWorkingDays: 5);

        $this->assertEquals(31, $this->service->calculateAnnualEntitlement($at5Years, 2026));
        $this->assertEquals(32, $this->service->calculateAnnualEntitlement($at10Years, 2026));
        $this->assertEquals(33, $this->service->calculateAnnualEntitlement($at15Years, 2026));
    }

    public function test_loyalty_bonus_is_capped_at_5_days(): void
    {
        $at25Years = $this->makeUser('2001-01-01', weeklyWorkingDays: 5);
        $at30Years = $this->makeUser('1996-01-01', weeklyWorkingDays: 5);

        $this->assertEquals(35, $this->service->calculateAnnualEntitlement($at25Years, 2026));
        $this->assertEquals(35, $this->service->calculateAnnualEntitlement($at30Years, 2026));
    }

    public function test_full_entitlement_combines_base_disability_and_loyalty_bonuses(): void
    {
        // 5-day week, severe disability, 10 years of service
        // Base: 30, Disability: +5, Loyalty: +2 → 37
        $user = $this->makeUser('2016-01-01', weeklyWorkingDays: 5, hasSevereDisability: true);

        $entitlement = $this->service->calculateAnnualEntitlement($user, 2026);

        $this->assertEquals(37, $entitlement);
    }

    public function test_throws_for_user_without_hire_date(): void
    {
        $user = new User([
            'hire_date' => null,
            'weekly_working_days' => 5,
            'has_severe_disability' => false,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->calculateAnnualEntitlement($user, 2026);
    }

    public function test_throws_for_zero_weekly_working_days(): void
    {
        $user = $this->makeUser('2024-01-01', weeklyWorkingDays: 0);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->calculateAnnualEntitlement($user, 2026);
    }

    public function test_throws_for_weekly_working_days_exceeding_seven(): void
    {
        $user = $this->makeUser('2024-01-01', weeklyWorkingDays: 8);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->calculateAnnualEntitlement($user, 2026);
    }

    private function makeUser(string $hireDate, int $weeklyWorkingDays = 5, bool $hasSevereDisability = false): User
    {
        return new User([
            'hire_date' => $hireDate,
            'weekly_working_days' => $weeklyWorkingDays,
            'has_severe_disability' => $hasSevereDisability,
        ]);
    }
}
