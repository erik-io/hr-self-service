<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AbsenceType;
use App\Models\LeaveRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Services\Leave\LeaveDurationCalculatorInterface;
use App\Services\Leave\LeaveEntitlementCalculatorInterface;
use App\Services\Leave\LeaveRequestServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly LeaveRequestServiceInterface $leaveRequestService,
        private readonly LeaveDurationCalculatorInterface $durationService,
        private readonly LeaveEntitlementCalculatorInterface $entitlementService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $leaveRequests = $request->user()
            ->leaveRequests()
            ->with(['absenceType', 'reviewer'])
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('leave-requests.index', compact('leaveRequests'));
    }

    public function show(LeaveRequest $leaveRequest): View
    {
        $this->authorize('view', $leaveRequest);

        $leaveRequest->load(['absenceType', 'reviewer', 'user']);

        return view('leave-requests.show', compact('leaveRequest'));
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $year = now()->year;
        $absenceTypes = AbsenceType::all();

        $totalEntitlement = $this->entitlementService->calculateAnnualEntitlement($user, $year);

        $usedRequests = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereYear('start_date', $year)
            ->whereHas('absenceType', function ($query) {
                $query->where('name', 'Vacation');
            })
            ->get();

        $usedDays = 0;
        foreach ($usedRequests as $leave) {
            $usedDays += $this->durationService->calculateNetDays(
                Carbon::parse($leave->start_date),
                Carbon::parse($leave->end_date)
            );
        }

        $remainingDays = $totalEntitlement - $usedDays;

        return view('leave-requests.create', compact('absenceTypes', 'remainingDays'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'absence_type_id' => ['required', 'exists:absence_types,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $user = $request->user();
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $absenceType = AbsenceType::find($validated['absence_type_id']);
        $isVacation = $absenceType->name === 'Vacation';

        $overlappingRequests = $this->leaveRequestService->getOverlappingRequests($user->id, $startDate, $endDate);

        if ($overlappingRequests->isNotEmpty()) {
            if ($isVacation) {
                foreach ($overlappingRequests as $overlap) {
                    if ($overlap->absenceType->name === 'Vacation') {
                        $formatStart = Carbon::parse($overlap->start_date)->format('Y-m-d');
                        $formatEnd = Carbon::parse($overlap->end_date)->format('Y-m-d');

                        return back()->withErrors([
                            'start_date' => "The requested period overlaps with an existing vacation from {$formatStart} to {$formatEnd}.",
                        ])->withInput();
                    }
                }
                session()->flash('warning', 'Note: Your vacation overlaps with another recorded absence type.');
            } else {
                session()->flash('warning', 'Note: Your request overlaps with another recorded absence.');
            }
        }

        $netDays = $this->durationService->calculateNetDays($startDate, $endDate);

        if ($isVacation) {
            $totalEntitlement = $this->entitlementService->calculateAnnualEntitlement($user, $startDate->year);

            $usedRequests = LeaveRequest::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereYear('start_date', $startDate->year)
                ->whereHas('absenceType', function ($query) {
                    $query->where('name', 'Vacation');
                })
                ->get();

            $usedDays = 0;
            foreach ($usedRequests as $leave) {
                $usedDays += $this->durationService->calculateNetDays(
                    Carbon::parse($leave->start_date),
                    Carbon::parse($leave->end_date)
                );
            }

            if (($usedDays + $netDays) > $totalEntitlement) {
                return back()->withErrors(['end_date' => 'You do not have enough remaining leave days.'])->withInput();
            }
        }

        LeaveRequest::create([
            'user_id' => $user->id,
            'absence_type_id' => $validated['absence_type_id'],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'pending',
        ]);

        if ($absenceType->name === 'Sick Leave' && $netDays > 3) {
            session()->flash('warning', 'Please submit a medical certificate for sick leave exceeding 3 days.');
        }

        return redirect()->route('dashboard')->with('success', 'Leave request submitted successfully.');
    }

    public function checkOccupancy(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $maxAbsences = 0;
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $count = LeaveRequest::whereIn('status', ['pending', 'approved'])
                ->where('start_date', '<=', $currentDate)
                ->where('end_date', '>=', $currentDate)
                ->count();

            if ($count > $maxAbsences) {
                $maxAbsences = $count;
            }
            $currentDate->addDay();
        }

        return response()->json([
            'max_absences' => $maxAbsences,
            'status' => $this->getOccupancyStatus($maxAbsences),
        ]);
    }

    private function getOccupancyStatus(int $count): array
    {
        if ($count >= 3) {
            return [
                'color' => 'bg-red-500',
                'border' => 'border-red-200',
                'heading' => __('High Occupancy'),
                'text' => __('Many colleagues are absent. Approval might be difficult.'),
            ];
        }

        if ($count >= 1) {
            return [
                'color' => 'bg-yellow-400',
                'border' => 'border-yellow-200',
                'heading' => __('Moderate Occupancy'),
                'text' => __('Some colleagues are already away during this period.'),
            ];
        }

        return [
            'color' => 'bg-green-500',
            'border' => 'border-green-200',
            'heading' => __('Low Occupancy'),
            'text' => __('No other absences recorded for this period.'),
        ];
    }
}
