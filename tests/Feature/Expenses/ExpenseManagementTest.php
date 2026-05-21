<?php

declare(strict_types=1);

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Number;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_view_pending_expenses_of_all_employees(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        $employee1 = User::factory()->create();
        $employee1->assignRole('employee');
        $expenseForEmployee1 = Expense::factory()->for($employee1)->pending()->create();

        $employee2 = User::factory()->create();
        $employee2->assignRole('employee');
        $expenseForEmployee2 = Expense::factory()->for($employee2)->pending()->create();

        $response = $this->actingAs($supervisor)->get(route('expenses.management.index'));

        $response->assertOk();
        $response->assertViewHas('expenses', function ($expenses) use ($expenseForEmployee1, $expenseForEmployee2) {
            return $expenses->contains($expenseForEmployee1)
                && $expenses->contains($expenseForEmployee2);
        });
    }

    public function test_management_index_view_can_be_rendered_by_supervisor(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $response = $this->actingAs($user)->get(route('expenses.management.index'));

        $response->assertOk();
        $response->assertViewIs('expenses.management.index');
        $response->assertSee(__('Expense Management'));
    }

    public function test_management_index_view_displays_only_pending_expenses(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $employee = User::factory()->create();
        $employee->assignRole('employee');

        Expense::factory()->for($employee)->create([
            'status' => Expense::STATUS_PENDING,
            'cost_center' => 'CC-PENDING',
        ]);

        Expense::factory()->for($employee)->create([
            'status' => Expense::STATUS_APPROVED,
            'cost_center' => 'CC-APPROVED',
        ]);

        $response = $this->actingAs($user)->get(route('expenses.management.index'));

        $response->assertOk();
        $response->assertSee('CC-PENDING');
        $response->assertDontSee('CC-APPROVED');
    }

    public function test_management_index_view_displays_employee_names(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $employee = User::factory()->create(['name' => 'John Doe']);
        $employee->assignRole('employee');

        Expense::factory()->for($employee)->create([
            'status' => Expense::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)->get(route('expenses.management.index'));

        $response->assertOk();
        $response->assertSee('John Doe');
    }

    public function test_management_index_view_has_view_details_links(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $expense = Expense::factory()->for($employee)->create([
            'status' => Expense::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)->get(route('expenses.management.index'));

        $response->assertOk();
        $response->assertSee(__('View Details'));
    }

    public function test_management_index_view_shows_empty_state_when_no_pending_expenses(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $response = $this->actingAs($user)->get(route('expenses.management.index'));

        $response->assertOk();
        $response->assertSee(__('There are no pending expense reports to review'));
    }

    public function test_management_index_view_has_view_history_button(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $response = $this->actingAs($user)->get(route('expenses.management.index'));

        $response->assertOk();
        $response->assertSee(__('View History'));
    }

    public function test_history_view_can_be_rendered_by_supervisor(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $response = $this->actingAs($user)->get(route('expenses.management.history'));

        $response->assertOk();
        $response->assertViewIs('expenses.management.history');
        $response->assertSee(__('Expense History'));
    }

    public function test_history_view_displays_all_expenses_by_default(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $employee = User::factory()->create();
        $employee->assignRole('employee');

        Expense::factory()->for($employee)->create([
            'status' => Expense::STATUS_PENDING,
            'cost_center' => 'CC-PENDING',
        ]);

        Expense::factory()->for($employee)->create([
            'status' => Expense::STATUS_APPROVED,
            'cost_center' => 'CC-APPROVED',
        ]);

        Expense::factory()->for($employee)->create([
            'status' => Expense::STATUS_REJECTED,
            'cost_center' => 'CC-REJECTED',
        ]);

        $response = $this->actingAs($user)->get(route('expenses.management.history'));

        $response->assertOk();
        $response->assertSee('CC-PENDING');
        $response->assertSee('CC-APPROVED');
        $response->assertSee('CC-REJECTED');
    }

    public function test_history_view_has_status_filter(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $response = $this->actingAs($user)->get(route('expenses.management.history'));

        $response->assertOk();
        $response->assertSee(__('Filter'));
        $response->assertSee('name="status"', false);
    }

    public function test_history_view_filters_by_approved_status(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $employee = User::factory()->create();
        $employee->assignRole('employee');

        Expense::factory()->for($employee)->create([
            'status' => Expense::STATUS_APPROVED,
            'cost_center' => 'CC-APPROVED',
        ]);

        Expense::factory()->for($employee)->create([
            'status' => Expense::STATUS_REJECTED,
            'cost_center' => 'CC-REJECTED',
        ]);

        $response = $this->actingAs($user)->get(route('expenses.management.history', ['status' => Expense::STATUS_APPROVED]));

        $response->assertOk();
        $response->assertSee('CC-APPROVED');
        $response->assertDontSee('CC-REJECTED');
    }

    public function test_history_view_has_view_pending_button(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $response = $this->actingAs($user)->get(route('expenses.management.history'));

        $response->assertOk();
        $response->assertSee(__('View Pending'));
    }

    public function test_history_view_shows_empty_state_when_no_expenses(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $response = $this->actingAs($user)->get(route('expenses.management.history'));

        $response->assertOk();
        $response->assertSee(__('No expenses yet'));
    }

    public function test_supervisor_can_view_approved_expenses_of_all_employees_in_history(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        $employee1 = User::factory()->create();
        $employee1->assignRole('employee');
        $expenseForEmployee1 = Expense::factory()->for($employee1)->approved()->create();

        $employee2 = User::factory()->create();
        $employee2->assignRole('employee');
        $expenseForEmployee2 = Expense::factory()->for($employee2)->approved()->create();

        $response = $this->actingAs($supervisor)->get(route('expenses.management.history'));

        $response->assertOk();
        $response->assertViewHas('expenses', function ($expenses) use ($expenseForEmployee1, $expenseForEmployee2) {
            return $expenses->contains($expenseForEmployee1)
                && $expenses->contains($expenseForEmployee2);
        });
    }

    public function test_supervisor_can_view_rejected_expenses_of_all_employees_in_history(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        $employee1 = User::factory()->create();
        $employee1->assignRole('employee');
        $expenseForEmployee1 = Expense::factory()->for($employee1)->rejected()->create();

        $employee2 = User::factory()->create();
        $employee2->assignRole('employee');
        $expenseForEmployee2 = Expense::factory()->for($employee2)->rejected()->create();

        $response = $this->actingAs($supervisor)->get(route('expenses.management.history'));

        $response->assertOk();
        $response->assertViewHas('expenses', function ($expenses) use ($expenseForEmployee1, $expenseForEmployee2) {
            return $expenses->contains($expenseForEmployee1)
                && $expenses->contains($expenseForEmployee2);
        });
    }

    public function test_supervisor_sees_expenses_of_all_employees_in_history(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        $employee1 = User::factory()->create();
        $employee1->assignRole('employee');
        $expenseForEmployee1 = Expense::factory()->for($employee1)->create();

        $employee2 = User::factory()->create();
        $employee2->assignRole('employee');
        $expenseForEmployee2 = Expense::factory()->for($employee2)->create();

        $response = $this->actingAs($supervisor)->get(route('expenses.management.history'));

        $response->assertOk();
        $response->assertViewHas('expenses', function ($expenses) use ($expenseForEmployee1, $expenseForEmployee2) {
            return $expenses->contains($expenseForEmployee1)
                && $expenses->contains($expenseForEmployee2);
        });
    }

    public function test_supervisor_index_shows_pending_expenses_default_sorted_oldest_first(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        $oldestExpense = Expense::factory()->pending()->create(['created_at' => now()->subDays(3)]);
        $middleExpense = Expense::factory()->pending()->create(['created_at' => now()->subDays(2)]);
        $newestExpense = Expense::factory()->pending()->create(['created_at' => now()->subDay()]);

        // Non-pending should not appear
        Expense::factory()->approved()->create();
        Expense::factory()->rejected()->create();

        $response = $this->actingAs($supervisor)
            ->get(route('expenses.management.index'));

        $response->assertOk();
        $response->assertViewHas('expenses', function ($paginator) use ($oldestExpense, $middleExpense, $newestExpense) {
            $items = array_values($paginator->items());
            $this->assertCount(3, $items);
            // Default for management pending is asc (oldest first)
            $this->assertSame($oldestExpense->id, $items[0]->id);
            $this->assertSame($middleExpense->id, $items[1]->id);
            $this->assertSame($newestExpense->id, $items[2]->id);

            return true;
        });
    }

    public function test_supervisor_history_list_can_filter_status(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        $pendingExpense = Expense::factory()->pending()->create(['created_at' => now()->subDays(3)]);
        $approvedExpense = Expense::factory()->approved()->create(['created_at' => now()->subDays(2)]);
        $rejectedExpense = Expense::factory()->rejected()->create(['created_at' => now()->subDay()]);

        // Filter only approved
        $this->actingAs($supervisor)
            ->get(route('expenses.management.history', ['status' => Expense::STATUS_APPROVED]))
            ->assertOk()
            ->assertViewHas('expenses', function ($paginator) {
                return collect($paginator->items())->every(fn ($expense) => $expense->status === Expense::STATUS_APPROVED);
            });

        // Filter only pending
        $this->actingAs($supervisor)
            ->get(route('expenses.management.history', ['status' => Expense::STATUS_PENDING]))
            ->assertOk()
            ->assertViewHas('expenses', function ($paginator) {
                return collect($paginator->items())->every(fn ($expense) => $expense->status === Expense::STATUS_PENDING);
            });

        // Filter only rejected
        $this->actingAs($supervisor)
            ->get(route('expenses.management.history', ['status' => Expense::STATUS_REJECTED]))
            ->assertOk()
            ->assertViewHas('expenses', function ($paginator) {
                return collect($paginator->items())->every(fn ($expense) => $expense->status === Expense::STATUS_REJECTED);
            });
    }

    public function test_supervisor_can_view_expense_details(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $expense = Expense::factory()->for($employee)->create();

        $response = $this->actingAs($supervisor)
            ->get(route('expenses.management.show', $expense));

        $response->assertOk();
        $response->assertViewIs('expenses.management.show');
        // Check if the specific expense is passed to the view
        $response->assertViewHas('expense', $expense);
    }

    public function test_supervisor_can_reject_with_comment_and_validation_enforced(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');
        $expense = Expense::factory()->pending()->create();

        // Missing comment -> validation error
        $this->actingAs($supervisor)
            ->from(route('expenses.management.show', $expense))
            ->patch(route('expenses.management.reject', $expense), [])
            ->assertRedirect(route('expenses.management.show', $expense))
            ->assertSessionHasErrors(['rejection_comment']);

        // Proper rejection
        $this->actingAs($supervisor)
            ->patch(route('expenses.management.reject', $expense), ['rejection_comment' => 'Invalid receipt'])
            ->assertRedirect(route('expenses.management.index'));

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => Expense::STATUS_REJECTED,
            'rejection_comment' => 'Invalid receipt',
        ]);
    }

    public function test_guest_is_redirected_and_non_supervisor_forbidden_for_management_routes(): void
    {
        // Guest
        $this->get(route('expenses.management.index'))
            ->assertRedirect(route('login'));

        $this->get(route('expenses.management.history'))
            ->assertRedirect(route('login'));

        // Authenticated but no supervisor role
        $user = User::factory()->create();
        $user->assignRole('employee');

        $this->actingAs($user)
            ->get(route('expenses.management.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('expenses.management.history'))
            ->assertForbidden();
    }

    public function test_supervisor_can_approve_a_pending_expense(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');
        $expense = Expense::factory()->pending()->create();

        $this->actingAs($supervisor)
            ->patch(route('expenses.management.approve', $expense))
            ->assertRedirect(route('expenses.management.index'));

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => Expense::STATUS_APPROVED,
        ]);
    }

    public function test_approving_an_already_approved_expense_does_not_change_status(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');
        $expense = Expense::factory()->approved()->create();

        $this->actingAs($supervisor)
            ->from(route('expenses.management.show', $expense))
            ->patch(route('expenses.management.approve', $expense))
            ->assertRedirect(route('expenses.management.show', $expense))
            ->assertSessionHas('error');

        $this->assertEquals(Expense::STATUS_APPROVED, $expense->fresh()->status);
    }

    public function test_approving_an_already_rejected_expense_does_not_change_status(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');
        $expense = Expense::factory()->rejected()->create();

        $this->actingAs($supervisor)
            ->from(route('expenses.management.show', $expense))
            ->patch(route('expenses.management.approve', $expense))
            ->assertRedirect(route('expenses.management.show', $expense))
            ->assertSessionHas('error');

        $this->assertEquals(Expense::STATUS_REJECTED, $expense->fresh()->status);
    }

    public function test_management_index_falls_back_to_defaults_with_invalid_query_params(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        Expense::factory(15)->pending()->create();

        $response = $this->actingAs($supervisor)
            ->get(route('expenses.management.index', [
                'sort_by' => 'invalid_column',
                'sort_direction' => 'weird',
                'per_page' => 9999,
            ]));

        $response->assertOk();
        $response->assertViewHas('expenses', function ($paginator) {
            // Default per page is 10
            $this->assertCount(10, $paginator->items());

            return true;
        });
    }

    public function test_employee_cannot_view_supervisor_show_route(): void
    {
        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $expense = Expense::factory()->for($employee)->create();

        $response = $this->actingAs($employee)
            ->get(route('expenses.management.show', $expense));

        // Should be forbidden by 'role:supervisor' middleware
        $response->assertStatus(403);
    }

    public function test_employee_cannot_approve_route(): void
    {
        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $expense = Expense::factory()->for($employee)->create();

        $response = $this->actingAs($employee)
            ->patch(route('expenses.management.approve', $expense));

        $response->assertStatus(403);
    }

    public function test_employee_cannot_reject_route(): void
    {
        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $expense = Expense::factory()->for($employee)->create();

        $response = $this->actingAs($employee)
            ->patch(route('expenses.management.reject', $expense), ['rejection_comment' => 'test']);

        $response->assertStatus(403);
    }

    public function test_supervisor_cannot_leave_comment_empty_on_rejected_expense(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        $expense = Expense::factory()->pending()->create();

        $this->actingAs($supervisor)
            ->from(route('expenses.management.index'))
            ->patch(route('expenses.management.reject', $expense), [
                'rejection_comment' => '',
            ])
            ->assertSessionHasErrors(['rejection_comment']);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => Expense::STATUS_PENDING,
            'rejection_comment' => null,
        ]);
    }

    public function test_supervisor_cannot_leave_comment_exceeding_max_length_on_rejected_expense(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        $expense = Expense::factory()->pending()->create();

        $this->actingAs($supervisor)
            ->patch(route('expenses.management.reject', $expense), [
                'rejection_comment' => str_repeat('X', Expense::MAX_REJECTION_COMMENT_LENGTH + 1),
            ])
            ->assertSessionHasErrors(['rejection_comment']);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => Expense::STATUS_PENDING,
            'rejection_comment' => null,
        ]);
    }

    public function test_supervisor_cannot_leave_comment_on_rejected_expense(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        $expense = Expense::factory()->create([
            'status' => Expense::STATUS_REJECTED,
            'rejection_comment' => 'Original reason',
        ]);

        $this->actingAs($supervisor)
            ->patch(route('expenses.management.reject', $expense), [
                'rejection_comment' => 'New reason',
            ]);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => Expense::STATUS_REJECTED,
            'rejection_comment' => 'Original reason',
        ]);
    }

    public function test_supervisor_cannot_leave_comment_on_approved_expense(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        $expense = Expense::factory()->create([
            'status' => Expense::STATUS_APPROVED,
            'rejection_comment' => null,
        ]);

        $this->actingAs($supervisor)
            ->patch(route('expenses.management.reject', $expense), [
                'rejection_comment' => 'Trying to reject an approved item',
            ]);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => Expense::STATUS_APPROVED, // Must stay approved
            'rejection_comment' => null,          // Must stay null
        ]);
    }

    public function test_supervisor_cannot_approve_rejected_expense(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        // Create an expense that is ALREADY rejected
        $expense = Expense::factory()->create([
            'status' => Expense::STATUS_REJECTED,
            'user_id' => User::factory(),
        ]);

        // Attempt to approve it
        $this->actingAs($supervisor)
            ->patch(route('expenses.management.approve', $expense));

        // Assert the database was NOT updated
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => Expense::STATUS_REJECTED, // Must stay rejected
        ]);
    }

    public function test_supervisor_cannot_reject_approved_expense(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        $expense = Expense::factory()->create([
            'status' => Expense::STATUS_APPROVED,
            'user_id' => User::factory(),
        ]);

        $this->actingAs($supervisor)
            ->patch(route('expenses.management.reject', $expense), [
                'rejection_comment' => 'Trying to flip status',
            ]);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => Expense::STATUS_APPROVED,
        ]);
    }

    public function test_management_show_view_displays_expense_details(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $employee = User::factory()->create(['name' => 'John Doe']);
        $employee->assignRole('employee');

        $expense = Expense::factory()->for($employee)->create([
            'amount' => 123.45,
            'expense_date' => '2023-01-15',
            'cost_center' => 'CC-SHOW-TEST',
            'status' => Expense::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)->get(route('expenses.management.show', $expense));

        $expectedLocale = app()->getLocale();
        $expectedAmount = Number::currency(123.45, in: 'EUR', locale: $expectedLocale);
        $expectedExpenseDate = Carbon::parse('2023-01-15')->translatedFormat('l, d F Y');

        $response->assertOk();
        $response->assertSee('John Doe');
        $response->assertSee($expectedAmount);
        $response->assertSee($expectedExpenseDate);
        $response->assertSee('CC-SHOW-TEST');
        $response->assertSee(__('Pending'));
    }

    public function test_management_show_view_has_approve_and_reject_buttons_for_pending_expense(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $expense = Expense::factory()->for($employee)->pending()->create();

        $response = $this->actingAs($user)->get(route('expenses.management.show', $expense));

        $response->assertOk();
        $response->assertSee(__('Approve'));
        $response->assertSee(__('Reject'));
    }

    public function test_management_show_view_does_not_have_action_buttons_for_approved_expense(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $expense = Expense::factory()->for($employee)->approved()->create();

        $response = $this->actingAs($user)->get(route('expenses.management.show', $expense));

        $response->assertOk();
        $response->assertDontSee(__('Actions'));
        $response->assertSee(__('Status'));
        $response->assertSee(__('This expense report has already been approved.'));
    }

    public function test_management_show_view_shows_rejection_comment_for_rejected_expense(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $expense = Expense::factory()->for($employee)->create([
            'status' => Expense::STATUS_REJECTED,
            'rejection_comment' => 'This is a test rejection comment.',
        ]);

        $response = $this->actingAs($user)->get(route('expenses.management.show', $expense));

        $response->assertOk();
        $response->assertSee('This is a test rejection comment.');
    }

    public function test_management_show_view_has_back_to_index_button(): void
    {
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $expense = Expense::factory()->for($employee)->create();

        $response = $this->actingAs($user)->get(route('expenses.management.show', $expense));

        $response->assertOk();
        $response->assertSee(route('expenses.management.index'));
    }
}
