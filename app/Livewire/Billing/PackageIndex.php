<?php

namespace App\Livewire\Billing;

use App\Enums\Role;
use App\Livewire\Auth\Register;
use App\Models\Package;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lets administrators define the packages each role can buy, and each role's trial. Changes apply to future purchases;
 * existing subscriptions keep the limits they started with.
 */
#[Title('Packages')]
class PackageIndex extends Component
{
    /**
     * Whether the form is open, and the package being edited (null for a new one).
     */
    public bool $editing = false;

    public ?int $packageId = null;

    public string $name = '';

    public string $description = '';

    public string $role = Role::Physician->value;

    public string $price = '0';

    public string $durationDays = '';

    public string $maxUploads = '';

    public string $maxPatientRecords = '';

    public string $maxStorageMb = '';

    public string $maxReports = '';

    public string $maxShares = '';

    public string $maxGuestLinks = '';

    public bool $isTrial = false;

    public bool $isActive = true;

    public ?string $status = null;

    /**
     * Form fields holding limits, mapped to their columns. A blank field means unlimited.
     */
    private const LIMIT_FIELDS = [
        'durationDays' => 'duration_days',
        'maxUploads' => 'max_uploads',
        'maxPatientRecords' => 'max_patient_records',
        'maxStorageMb' => 'max_storage_mb',
        'maxReports' => 'max_reports',
        'maxShares' => 'max_shares',
        'maxGuestLinks' => 'max_guest_links',
    ];

    public function mount(): void
    {
        $this->authorize('manage-billing');
    }

    public function create(): void
    {
        $this->authorize('manage-billing');

        $this->resetForm();
        $this->editing = true;
    }

    public function edit(int $packageId): void
    {
        $this->authorize('manage-billing');

        $package = Package::findOrFail($packageId);

        $this->resetForm();
        $this->editing = true;
        $this->packageId = $package->id;
        $this->name = $package->name;
        $this->description = $package->description ?? '';
        $this->role = $package->role->value;
        $this->price = (string) $package->price;
        $this->isTrial = $package->is_trial;
        $this->isActive = $package->is_active;

        foreach (self::LIMIT_FIELDS as $field => $column) {
            $this->{$field} = $package->{$column} === null ? '' : (string) $package->{$column};
        }
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->authorize('manage-billing');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'role' => ['required', Rule::in(array_map(fn (Role $role) => $role->value, Register::selfServiceRoles()))],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'durationDays' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'maxUploads' => ['nullable', 'integer', 'min:0'],
            'maxPatientRecords' => ['nullable', 'integer', 'min:0'],
            'maxStorageMb' => ['nullable', 'integer', 'min:0'],
            'maxReports' => ['nullable', 'integer', 'min:0'],
            'maxShares' => ['nullable', 'integer', 'min:0'],
            'maxGuestLinks' => ['nullable', 'integer', 'min:0'],
            'isTrial' => ['boolean'],
            'isActive' => ['boolean'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'description' => filled($validated['description']) ? $validated['description'] : null,
            'role' => $validated['role'],
            'price' => $this->isTrial ? 0 : (int) $validated['price'],
            'is_trial' => $this->isTrial,
            'is_active' => $this->isActive,
        ];

        foreach (self::LIMIT_FIELDS as $field => $column) {
            $attributes[$column] = filled($validated[$field]) ? (int) $validated[$field] : null;
        }

        DB::transaction(function () use ($attributes): void {
            $package = Package::updateOrCreate(['id' => $this->packageId], $attributes);

            if ($package->is_trial) {
                Package::query()->where('role', $package->role)->whereKeyNot($package->id)->update(['is_trial' => false]);
            }
        });

        $this->status = $this->packageId ? 'Package updated. Existing subscribers keep the limits they started with.' : 'Package created.';
        $this->resetForm();
    }

    public function toggleActive(int $packageId): void
    {
        $this->authorize('manage-billing');

        $package = Package::findOrFail($packageId);
        $package->update(['is_active' => ! $package->is_active]);

        $this->status = $package->is_active ? "{$package->name} is available again." : "{$package->name} is no longer offered.";
    }

    /**
     * Delete a package nobody has bought. Packages with purchases can only be withdrawn, to keep the history.
     */
    public function delete(int $packageId): void
    {
        $this->authorize('manage-billing');

        $package = Package::findOrFail($packageId);

        if ($package->subscriptions()->exists()) {
            $this->addError('packages', "{$package->name} has been bought, so it cannot be deleted. Withdraw it instead.");

            return;
        }

        $package->delete();
        $this->status = "{$package->name} was deleted.";
    }

    private function resetForm(): void
    {
        $this->reset('editing', 'packageId', 'name', 'description', 'role', 'price', 'isTrial', 'isActive', ...array_keys(self::LIMIT_FIELDS));
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.billing.package-index', [
            'packagesByRole' => Package::query()
                ->withCount(['subscriptions as subscriber_count' => fn ($query) => $query->current()])
                ->orderByDesc('is_trial')
                ->orderBy('price')
                ->get()
                ->groupBy(fn (Package $package) => $package->role->value),
            'roles' => Register::selfServiceRoles(),
            'currency' => config('billing.currency'),
        ]);
    }
}
