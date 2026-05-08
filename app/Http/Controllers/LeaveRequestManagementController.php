<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Services\Leave\LeaveRequestServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class LeaveRequestManagementController extends Controller
{
    public function __construct(
        private readonly LeaveRequestServiceInterface $leaveRequestService
    ) {}

    public function index(): View
    {
        $leaveRequests = LeaveRequest::with(['user', 'absenceType'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('leave-requests.management.index', compact('leaveRequests'));
    }

    public function show(LeaveRequest $leaveRequest): View
    {
        $leaveRequest->load(['user', 'absenceType']);

        $startDate = Carbon::parse($leaveRequest->start_date);
        $endDate = Carbon::parse($leaveRequest->end_date);

        $teamOverlaps = $this->leaveRequestService->getTeamOverlappingRequests(
            $startDate,
            $endDate,
            $leaveRequest->user_id
        );

        return view('leave-requests.management.show', compact('leaveRequest', 'teamOverlaps'));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->leaveRequestService->approve($leaveRequest, $request->user()->id);

        return redirect()->route('leave-requests.management.index')
            ->with('success', __('leave-requests.feedback.approved'));
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5'],
        ]);

        $this->leaveRequestService->reject($leaveRequest, $request->user()->id, $validated['rejection_reason']);

        return redirect()->route('leave-requests.management.index')
            ->with('success', __('leave-requests.feedback.rejected'));
    }
}
