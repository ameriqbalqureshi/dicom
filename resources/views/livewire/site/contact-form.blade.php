<div>
    @if ($sent)
        <div class="flex flex-col items-start gap-4 rounded-2xl border border-emerald-800 bg-emerald-950/40 p-8">
            <h2 class="text-xl font-semibold text-white">Thank you, your message has been sent.</h2>
            <p class="text-sm text-emerald-200">We usually reply within one working day.</p>
            <button type="button" wire:click="$set('sent', false)" class="text-sm text-emerald-300 hover:text-white">Send another message</button>
        </div>
    @else
        <form wire:submit="send" class="flex flex-col gap-5 rounded-2xl border border-slate-800 bg-slate-900/60 p-6 sm:p-8">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="contact-name" class="text-sm text-slate-300">Your name</label>
                    <input wire:model="name" id="contact-name" type="text" autocomplete="name" required
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                    @error('name') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="contact-email" class="text-sm text-slate-300">Email</label>
                    <input wire:model="email" id="contact-email" type="email" autocomplete="email" required
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                    @error('email') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="contact-organisation" class="text-sm text-slate-300">Hospital or practice <span class="text-slate-500">(optional)</span></label>
                    <input wire:model="organisation" id="contact-organisation" type="text" autocomplete="organization"
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                    @error('organisation') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="contact-topic" class="text-sm text-slate-300">Topic</label>
                    <select wire:model="topic" id="contact-topic"
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                        @foreach ($topics as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('topic') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="contact-message" class="text-sm text-slate-300">Message</label>
                <textarea wire:model="message" id="contact-message" rows="6" required
                    class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none"></textarea>
                @error('message') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="contact-website">Leave this field empty</label>
                <input wire:model="website" id="contact-website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <button type="submit" class="self-start rounded-md bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-500 disabled:opacity-60">
                <span wire:loading.remove wire:target="send">Send message</span>
                <span wire:loading wire:target="send">Sending…</span>
            </button>
        </form>
    @endif
</div>
