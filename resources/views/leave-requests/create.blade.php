<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('New Leave Request') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg border-l-4 border-indigo-500">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('Your Vacation Status') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('Overview of your leave days for the current year.') }}
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="block text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                            {{ $remainingDays ?? '--' }}
                        </span>
                        <span class="text-xs uppercase tracking-widest text-gray-500 dark:text-gray-500">
                            {{ __('Days Remaining') }}
                        </span>
                    </div>
                </div>
            </div>

            @if (session('warning'))
                <div
                    class="p-4 text-sm text-yellow-800 rounded-lg bg-yellow-50 dark:bg-gray-800 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-900"
                    role="alert">
                    <span class="font-bold">{{ __('Note:') }}</span> {{ session('warning') }}
                </div>
            @endif

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <form method="post" action="{{ route('leave-requests.store') }}" class="space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="absence_type_id" :value="__('Absence Type')"/>
                            <select id="absence_type_id" name="absence_type_id"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                @foreach($absenceTypes as $type)
                                    <option
                                        value="{{ $type->id }}" {{ old('absence_type_id') == $type->id ? 'selected' : '' }}>
                                        {{ __($type->name) }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('absence_type_id')"/>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="start_date" :value="__('Start Date')"/>
                                <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full"
                                              :value="old('start_date')" required/>
                                <x-input-error class="mt-2" :messages="$errors->get('start_date')"/>
                            </div>

                            <div>
                                <x-input-label for="end_date" :value="__('End Date')"/>
                                <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full"
                                              :value="old('end_date')" required/>
                                <x-input-error class="mt-2" :messages="$errors->get('end_date')"/>
                            </div>
                        </div>

                        <div id="occupancy-indicator"
                             class="hidden p-4 rounded-lg border flex items-start space-x-3 transition-opacity duration-300">
                            <div id="status-dot" class="mt-1 h-3 w-3 rounded-full flex-shrink-0 shadow-sm"></div>
                            <div>
                                <p id="status-heading" class="text-sm font-bold text-gray-900 dark:text-gray-100"></p>
                                <p id="status-text" class="text-xs text-gray-600 dark:text-gray-400"></p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                            <x-primary-button>{{ __('Submit Request') }}</x-primary-button>

                            <a href="{{ route('dashboard') }}"
                               class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 underline">
                                {{ __('Cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const startDateInput = document.getElementById('start_date');
                const endDateInput = document.getElementById('end_date');
                const indicator = document.getElementById('occupancy-indicator');
                const dot = document.getElementById('status-dot');
                const heading = document.getElementById('status-heading');
                const text = document.getElementById('status-text');

                async function updateOccupancy() {
                    const start = startDateInput.value;
                    const end = endDateInput.value;

                    if (!start || !end) return;

                    try {
                        const response = await fetch(`{{ route('leave-requests.check-occupancy') }}?start_date=${start}&end_date=${end}`);
                        const data = await response.json();

                        indicator.classList.remove('hidden', 'border-red-200', 'border-yellow-200', 'border-green-200');
                        indicator.classList.add(data.status.border);

                        dot.classList.remove('bg-red-500', 'bg-yellow-400', 'bg-green-500');
                        dot.classList.add(data.status.color);

                        heading.textContent = data.status.heading;
                        text.textContent = data.status.text;
                    } catch (error) {
                        console.error('Error fetching occupancy:', error);
                    }
                }

                startDateInput.addEventListener('change', updateOccupancy);
                endDateInput.addEventListener('change', updateOccupancy);
            });
        </script>
    @endpush
</x-app-layout>
