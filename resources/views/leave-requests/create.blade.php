<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Absence Management') }}
            </h2>
            <div class="mt-2 flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('Remaining Vacation Days') }}:</span>
                <span
                    class="text-lg font-semibold text-indigo-600 dark:text-indigo-400">{{ $remainingDays ?? '--' }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('warning'))
                <div
                    class="p-4 text-sm text-yellow-800 rounded-lg bg-yellow-50 dark:bg-gray-800 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-900"
                    role="alert">
                    <span class="font-bold">{{ __('Note:') }}</span> {{ session('warning') }}
                </div>
            @endif

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg max-w-xl mx-auto">
                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('Create New Request') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('Provide the details for your new absence request.') }}
                        </p>
                    </div>

                    <form method="post" action="{{ route('leave-requests.store') }}" class="space-y-6"
                          enctype="multipart/form-data">
                        @csrf

                        <div>
                            <x-input-label for="absence_type_id" :value="__('Absence Type')"/>
                            <select id="absence_type_id" name="absence_type_id"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                @foreach($absenceTypes as $type)
                                    <option
                                        value="{{ $type->id }}"
                                        data-illness="{{ in_array($type->name, ['Sick Leave', 'Illness', 'Krankheit'], true) ? 'true' : 'false' }}"
                                        {{ old('absence_type_id') == $type->id ? 'selected' : '' }}>
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

                        <div id="medical-certificate-toggle" class="hidden">
                            <div class="flex items-start gap-3">
                                <input id="attach_medical_certificate" name="attach_medical_certificate" type="checkbox"
                                       class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:focus:ring-indigo-600">
                                <div>
                                    <label for="attach_medical_certificate"
                                           class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ __('Attach Medical Certificate') }}
                                    </label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ __('Required for longer illness absences. PDF or image files only.') }}
                                    </p>
                                </div>
                            </div>

                            <div id="medical-certificate-upload" class="hidden mt-3">
                                <x-input-label for="medical_certificate" :value="__('Medical Certificate File')"/>
                                <div class="mt-1 flex items-center gap-3 flex-nowrap">
                                    <input id="medical_certificate" name="medical_certificate" type="file"
                                           accept=".pdf,image/*"
                                           class="block w-full min-w-0 flex-1 text-sm text-gray-600 dark:text-gray-300 file:mr-4 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900/30 dark:file:text-indigo-200">
                                    <button type="button"
                                            class="inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600">
                                        {{ __('Upload File') }}
                                    </button>
                                </div>
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('Upload action is not available in this form.') }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ __('Team Availability Check') }}
                                </h4>
                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                    {{ __('The system checks approved requests and team capacity for the selected period.') }}
                                </p>
                            </div>

                            <div id="occupancy-empty"
                                 class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-3 text-xs text-gray-500 dark:text-gray-400">
                                {{ __('Select a start and end date to see availability.') }}
                            </div>

                            <div id="occupancy-indicator"
                                 class="hidden p-4 rounded-lg border flex items-start space-x-3 transition-opacity duration-300">
                                <div id="status-dot" class="mt-1 h-3 w-3 rounded-full flex-shrink-0 shadow-sm"></div>
                                <div>
                                    <p id="status-heading"
                                       class="text-sm font-bold text-gray-900 dark:text-gray-100"></p>
                                    <p id="status-text" class="text-xs text-gray-600 dark:text-gray-400"></p>
                                </div>
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ __('Blocked status also applies if your request overlaps with an existing approved absence.') }}
                            </p>
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

    @php
        $statusCopy = [
            'bg-green-500' => [
                'heading' => __('Available'),
                'text' => __('Team capacity is sufficient for this period.'),
            ],
            'bg-yellow-400' => [
                'heading' => __('Warning'),
                'text' => __('Many colleagues are away and capacity may be tight.'),
            ],
            'bg-red-500' => [
                'heading' => __('Blocked'),
                'text' => __('Capacity is critical or your request overlaps an approved absence.'),
            ],
        ];
    @endphp

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const absenceTypeSelect = document.getElementById('absence_type_id');
            const medicalToggle = document.getElementById('medical-certificate-toggle');
            const medicalCheckbox = document.getElementById('attach_medical_certificate');
            const medicalUpload = document.getElementById('medical-certificate-upload');
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const emptyState = document.getElementById('occupancy-empty');
            const indicator = document.getElementById('occupancy-indicator');
            const dot = document.getElementById('status-dot');
            const heading = document.getElementById('status-heading');
            const text = document.getElementById('status-text');

            const statusCopy = @json($statusCopy);

            function updateMedicalFields() {
                const selectedOption = absenceTypeSelect.options[absenceTypeSelect.selectedIndex];
                const isIllness = selectedOption?.dataset.illness === 'true';

                medicalToggle.classList.toggle('hidden', !isIllness);

                if (!isIllness) {
                    medicalCheckbox.checked = false;
                    medicalUpload.classList.add('hidden');
                }
            }

            function updateMedicalUpload() {
                medicalUpload.classList.toggle('hidden', !medicalCheckbox.checked);
            }

            async function updateOccupancy() {
                const start = startDateInput.value;
                const end = endDateInput.value;

                if (!start || !end) {
                    indicator.classList.add('hidden');
                    emptyState.classList.remove('hidden');
                    return;
                }

                try {
                    const response = await fetch(`{{ route('leave-requests.check-occupancy') }}?start_date=${start}&end_date=${end}`);
                    const data = await response.json();

                    indicator.classList.remove('hidden', 'border-red-200', 'border-yellow-200', 'border-green-200');
                    indicator.classList.add(data.status.border);
                    emptyState.classList.add('hidden');

                    dot.classList.remove('bg-red-500', 'bg-yellow-400', 'bg-green-500');
                    dot.classList.add(data.status.color);

                    heading.textContent = statusCopy[data.status.color]?.heading ?? data.status.heading;
                    text.textContent = statusCopy[data.status.color]?.text ?? data.status.text;
                } catch (error) {
                    console.error('Error fetching occupancy:', error);
                }
            }

            updateMedicalFields();
            updateMedicalUpload();

            absenceTypeSelect.addEventListener('change', updateMedicalFields);
            medicalCheckbox.addEventListener('change', updateMedicalUpload);
            startDateInput.addEventListener('change', updateOccupancy);
            endDateInput.addEventListener('change', updateOccupancy);
        });
    </script>
</x-app-layout>
