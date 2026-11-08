<?php

namespace App\Livewire\Site;

use App\Enums\MarketingApplicationStatus;
use App\Models\MarketingApplication;
use App\Models\User;
use App\Notifications\MarketingApplicationSubmitted;
use App\Services\BestEffortNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * The public form for applying to become a marketing officer. Like the public receipt form, it says the same thing
 * whether or not the address already has an account, so it never reveals who uses the service. Applying again before a
 * decision replaces the earlier application.
 */
class MarketingApplicationForm extends Component
{
    /**
     * Applications one visitor may send before having to wait.
     */
    public const MAX_ATTEMPTS = 3;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $city = '';

    public string $experience = '';

    /**
     * A field hidden from people; bots that fill it in are ignored.
     */
    public string $website = '';

    public bool $sent = false;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:255'],
            'experience' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function submit(): void
    {
        $validated = $this->validate();

        $throttleKey = 'marketing-application:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'You have applied several times already. Please try again in '.ceil(RateLimiter::availableIn($throttleKey) / 60).' minutes.',
            ]);
        }

        RateLimiter::hit($throttleKey, 3600);

        if ($this->website === '') {
            $application = MarketingApplication::query()->updateOrCreate(
                ['email' => mb_strtolower(trim($validated['email'])), 'status' => MarketingApplicationStatus::Pending],
                [
                    'name' => trim($validated['name']),
                    'phone' => trim($validated['phone']),
                    'city' => filled($validated['city']) ? trim($validated['city']) : null,
                    'experience' => filled($validated['experience']) ? trim($validated['experience']) : null,
                ],
            );

            BestEffortNotifier::send(User::admins()->get(), new MarketingApplicationSubmitted($application));
        }

        $this->reset('name', 'email', 'phone', 'city', 'experience');
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.site.marketing-application-form');
    }
}
