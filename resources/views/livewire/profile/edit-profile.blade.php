<div class="mx-auto flex max-w-2xl flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Profile</h1>
        <p class="mt-1 text-sm text-slate-400">These details are printed under your name on reports you sign. Reports you have already signed keep the details they were signed with.</p>
    </div>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    <form wire:submit="save" class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
        <label class="flex flex-col gap-1 text-sm">
            <span class="text-slate-300">Qualifications</span>
            <input wire:model="qualifications" type="text" placeholder="e.g. MBBS, FCPS (Cardiology)"
                class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 focus:border-sky-500 focus:outline-none">
            @error('qualifications') <span class="text-red-400">{{ $message }}</span> @enderror
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span class="text-slate-300">Registration number</span>
            <input wire:model="registrationNumber" type="text"
                class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 focus:border-sky-500 focus:outline-none">
            @error('registrationNumber') <span class="text-red-400">{{ $message }}</span> @enderror
        </label>

        <div class="flex flex-col gap-2 text-sm">
            <span class="text-slate-300">Signature</span>

            @if ($signature?->isPreviewable() && ! $errors->has('signature'))
                <img src="{{ $signature->temporaryUrl() }}" alt="New signature" class="max-h-24 self-start rounded bg-white p-2">
            @elseif ($signaturePreview)
                <div class="flex items-center gap-4">
                    <img src="{{ $signaturePreview }}" alt="Current signature" class="max-h-24 rounded bg-white p-2">
                    <button type="button" wire:click="removeSignature" wire:confirm="Remove your signature image?" class="text-red-400 hover:text-red-300">Remove</button>
                </div>
            @endif

            <input wire:model="signature" type="file" accept="image/png,image/jpeg" class="text-slate-300">
            <span class="text-xs text-slate-500">PNG or JPEG, up to 1 MB. A scan on a white or transparent background works best.</span>
            @error('signature') <span class="text-red-400">{{ $message }}</span> @enderror
        </div>

        <div>
            <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">Save</button>
        </div>
    </form>
</div>
