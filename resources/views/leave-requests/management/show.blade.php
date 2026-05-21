@php use Carbon\Carbon; @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('leave-requests.manage') }} #{{ $leaveRequest->id }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-2xl">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ __('leave-requests.details') }}</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Employee') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $leaveRequest->user->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('leave-requests.absence_type') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ __($leaveRequest->absenceType->name) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('leave-requests.start_date') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ Carbon::parse($leaveRequest->start_date)->locale(app()->getLocale())->isoFormat('L') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('leave-requests.end_date') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ Carbon::parse($leaveRequest->end_date)->locale(app()->getLocale())->isoFormat('L') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('leave-requests.status') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 uppercase font-bold">{{ ucfirst($leaveRequest->status) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div
                class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg border-l-4 {{ $teamOverlaps->isNotEmpty() ? 'border-red-500' : 'border-green-500' }}">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">{{ __('Team Capacity Check') }}</h3>

                @if ($teamOverlaps->isNotEmpty())
                    <p class="text-sm text-red-600 dark:text-red-400 mb-4 font-semibold">
                        {{ __('Warning: The following team members have approved or pending leave during this period.') }}
                    </p>
                    <ul class="space-y-2">
                        @foreach ($teamOverlaps as $overlap)
                            <li class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 p-2 rounded">
                                <strong>{{ $overlap->user->name }}</strong>: {{ $overlap->absenceType->name }}
                                ({{ Carbon::parse($overlap->start_date)->format('Y-m-d') }}
                                to {{ Carbon::parse($overlap->end_date)->format('Y-m-d') }}) -
                                <em>{{ ucfirst($overlap->status) }}</em>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-green-600 dark:text-green-400 font-semibold">
                        {{ __('No overlaps detected. The team is fully staffed during this period.') }}
                    </p>
                @endif
            </div>

            @if ($leaveRequest->status === 'pending')
                <div
                    x-data="{
                        showRejectForm: {{ $errors->has('rejection_reason') ? 'true' : 'false' }},
                        rejectionReason: @js(old('rejection_reason', ''))
                    }"
                    class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg"
                >
                    @if ($errors->any() && !$errors->has('rejection_reason'))
                        <div
                            class="mb-4 p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 rounded">
                            <strong>{{ __('Error') }}</strong>
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ __('Actions') }}</h3>

                    <div x-show="!showRejectForm" x-transition class="flex flex-col sm:flex-row gap-4">
                        <form method="POST" action="{{ route('leave-requests.management.approve', $leaveRequest) }}"
                              class="flex-1">
                            @csrf
                            @method('PATCH')
                            <x-primary-button
                                class="w-full justify-center bg-green-600 hover:bg-green-700 focus:bg-green-700 active:bg-green-800 focus:ring-green-500">
                                {{ __('Approve') }}
                            </x-primary-button>
                        </form>

                        <x-danger-button type="button" class="w-full justify-center sm:flex-1"
                                         @click="showRejectForm = true">
                            {{ __('Reject') }}
                        </x-danger-button>
                    </div>

                    <form method="POST"
                          action="{{ route('leave-requests.management.reject', $leaveRequest) }}"
                          x-show="showRejectForm"
                          x-cloak
                          x-transition
                          x-ref="rejectForm"
                          class="mt-4">
                        @csrf
                        @method('PATCH')

                        <div>
                            <x-input-label for="rejection_reason" :value="__('leave-requests.rejection_reason')"/>
                            <textarea id="rejection_reason"
                                      name="rejection_reason"
                                      rows="4"
                                      class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                      x-model="rejectionReason"
                                      required minlength="5">{{ old('rejection_reason') }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('rejection_reason')"/>
                        </div>

                        <div class="mt-4 flex flex-col sm:flex-row gap-4">
                            <x-danger-button type="submit" class="w-full justify-center sm:flex-1">
                                {{ __('Confirm Rejection') }}
                            </x-danger-button>

                            <x-secondary-button type="button" class="w-full justify-center sm:flex-1"
                                                @click="showRejectForm = false">
                                {{ __('Cancel') }}
                            </x-secondary-button>
                        </div>
                    </form>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
