<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Livewire\Auth\Register;
use App\Models\Package;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The public pricing page: the active packages administrators have set up for each role people can sign up as.
 */
class PricingController extends Controller
{
    public function __invoke(Request $request): View
    {
        $roles = Register::selfServiceRoles();
        $selectedRole = Role::tryFrom((string) $request->query('for')) ?? $roles[0];

        if (! in_array($selectedRole, $roles, true)) {
            $selectedRole = $roles[0];
        }

        return view('site.pricing', [
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'packages' => Package::query()
                ->active()
                ->where('role', $selectedRole)
                ->orderByDesc('is_trial')
                ->orderBy('price')
                ->get(),
            'currency' => config('billing.currency'),
        ]);
    }
}
