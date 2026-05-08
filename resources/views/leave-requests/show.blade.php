@php use Carbon\Carbon; @endphp
<x-app-layout>
    <x-slot name="title">
        {{ __('leave-requests.details') }}
    </x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('leave-requests.details') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('leave-requests.my_requests') }}
                </p>
            </div>

            <a href="{{ route('leave-requests.index') }}"
               class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 underline">
                {{ __('leave-requests.back_to_requests') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @php
                $statusLabel = match ($leaveRequest->status) {
                    'approved' => __('Approved'),
                    'rejected' => __('Rejected'),
                    default => __('Pending'),
                };
                $statusClasses = match ($leaveRequest->status) {
                    'approved' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                    'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                    default => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                };
            @endphp

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-6">
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 dark:border-gray-700 pb-6">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('leave-requests.type') }}</p>
                            <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ __($leaveRequest->absenceType->name) }}
                            </h3>
                        </div>

                        <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full {{ $statusClasses }}">
                            {{ $statusLabel }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">{{ __('leave-requests.period') }}</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-gray-100">
                                {{ Carbon::parse($leaveRequest->start_date)->locale(app()->getLocale())->isoFormat('L') }}
                                - {{ Carbon::parse($leaveRequest->end_date)->locale(app()->getLocale())->isoFormat('L') }}
                            </p>
                        </div>

                        <div>
                            <p class="text-gray-500 dark:text-gray-400">{{ __('leave-requests.submitted_on') }}</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-gray-100">
                                {{ Carbon::parse($leaveRequest->created_at)->locale(app()->getLocale())->isoFormat('L') }}
                            </p>
                        </div>

                        <div>
                            <p class="text-gray-500 dark:text-gray-400">{{ __('leave-requests.reviewer') }}</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-gray-100">
                                {{ $leaveRequest->reviewer?->name ?? '—' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-gray-500 dark:text-gray-400">{{ __('leave-requests.status') }}</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-gray-100">
                                {{ $statusLabel }}
                            </p>
                        </div>
                    </div>

                    @if($leaveRequest->status === 'rejected' && filled($leaveRequest->rejection_reason))
                        <div class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                            <h4 class="text-sm font-semibold text-red-800 dark:text-red-300">
                                {{ __('leave-requests.rejection_reason') }}
                            </h4>
                            <p class="mt-2 text-sm text-red-900 dark:text-red-200 whitespace-pre-line">
                                {{ $leaveRequest->rejection_reason }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

