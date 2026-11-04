<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-1">
        <a href="{{ $patientRecord ? route('patient-records.show', $patientRecord) : route('patient-records.index') }}" class="text-sm text-slate-400 hover:text-white">
            &larr; {{ $patientRecord ? $patientRecord->name : 'Patients' }}
        </a>
        <h1 class="text-2xl font-semibold tracking-tight">{{ $patientRecord ? 'Edit patient' : 'New patient' }}</h1>
        <p class="text-sm text-slate-400">Only the name is required. Add the rest whenever you have it.</p>
    </div>

    @error('limit')
        <p class="rounded-md border border-red-800 bg-red-950 px-4 py-2 text-sm text-red-300">{{ $message }}</p>
    @enderror

    <form wire:submit="save" class="flex max-w-4xl flex-col gap-6">
        <fieldset class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
            <legend class="px-1 font-semibold">Patient</legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Name</span>
                    <input wire:model="name" type="text" required class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('name') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Medical record number</span>
                    <input wire:model="medicalRecordNumber" type="text" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('medicalRecordNumber') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Date of birth</span>
                    <input wire:model="dateOfBirth" type="date" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('dateOfBirth') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Sex</span>
                    <select wire:model="sex" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                        <option value="">Not recorded</option>
                        @foreach ($sexes as $sexOption)
                            <option value="{{ $sexOption->value }}">{{ $sexOption->label() }}</option>
                        @endforeach
                    </select>
                    @error('sex') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Phone</span>
                    <input wire:model="phone" type="tel" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('phone') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>
            </div>
        </fieldset>

        <fieldset class="flex flex-col gap-3 rounded-xl border border-slate-800 bg-slate-900 p-6">
            <legend class="px-1 font-semibold">Risk factors</legend>

            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-300">
                @foreach ($riskFactorOptions as $riskFactor)
                    <label wire:key="risk-{{ $riskFactor->value }}" class="flex items-center gap-2">
                        <input wire:model="riskFactors" type="checkbox" value="{{ $riskFactor->value }}" class="rounded border-slate-600 bg-slate-950 accent-sky-500">
                        {{ $riskFactor->label() }}
                    </label>
                @endforeach
            </div>
            @error('riskFactors.*') <span class="text-sm text-red-400">{{ $message }}</span> @enderror
        </fieldset>

        <fieldset class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
            <legend class="px-1 font-semibold">Clinical details</legend>

            @foreach ($sections as $section => $label)
                <label wire:key="section-{{ $section }}" class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">{{ $label }}</span>
                    <textarea wire:model="clinical.{{ $section }}" rows="3" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 focus:border-sky-500 focus:outline-none"></textarea>
                    @error('clinical.'.$section) <span class="text-red-400">{{ $message }}</span> @enderror
                </label>
            @endforeach
        </fieldset>

        <div class="flex items-center gap-4">
            <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">Save patient</button>
            <a href="{{ $patientRecord ? route('patient-records.show', $patientRecord) : route('patient-records.index') }}" class="text-sm text-slate-400 hover:text-white">Cancel</a>
        </div>
    </form>
</div>
