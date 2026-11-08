<div>
    @if ($sent)
        <div class="flex flex-col items-start gap-4 rounded-2xl border border-emerald-800 bg-emerald-950/40 p-8">
            <h2 class="text-xl font-semibold text-white">Thank you, we have your application.</h2>
            <p class="text-sm text-emerald-200">We will review it and email you with our decision. If you are approved, the email will tell you how to set your password.</p>
        </div>
    @else
        <form wire:submit="submit" class="flex flex-col gap-5 rounded-2xl border border-slate-800 bg-slate-900/60 p-6 sm:p-8">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="application-name" class="text-sm text-slate-300">Full name</label>
                    <input wire:model="name" id="application-name" type="text" autocomplete="name" required
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                    @error('name') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="application-email" class="text-sm text-slate-300">Email</label>
                    <input wire:model="email" id="application-email" type="email" autocomplete="email" required
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                    @error('email') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="application-phone" class="text-sm text-slate-300">Phone</label>
                    <input wire:model="phone" id="application-phone" type="tel" autocomplete="tel" required
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                    @error('phone') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="application-city" class="text-sm text-slate-300">City <span class="text-slate-500">(optional)</span></label>
                    <input wire:model="city" id="application-city" type="text" autocomplete="address-level2"
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                    @error('city') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="application-experience" class="text-sm text-slate-300">Your experience <span class="text-slate-500">(optional: hospitals or doctors you work with, past sales or marketing work)</span></label>
                <textarea wire:model="experience" id="application-experience" rows="5"
                    class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none"></textarea>
                @error('experience') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="application-website">Leave this field empty</label>
                <input wire:model="website" id="application-website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <button type="submit" class="self-start rounded-md bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-500 disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">Send application</span>
                <span wire:loading wire:target="submit">Sending…</span>
            </button>
        </form>
    @endif
</div>
