<?php

use App\Http\Controllers\DicomInstanceFileController;
use App\Http\Controllers\GuestInstanceFileController;
use App\Http\Controllers\GuestReportPdfController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\ReportPdfController;
use App\Http\Controllers\VerifyEmailController;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmail;
use App\Livewire\Billing\MyPackage;
use App\Livewire\Billing\PackageIndex;
use App\Livewire\Billing\PaymentIndex;
use App\Livewire\Guest\GuestStudy;
use App\Livewire\Profile\EditProfile;
use App\Livewire\Studies\PendingShares;
use App\Livewire\Studies\StudyIndex;
use App\Livewire\Studies\StudyShow;
use App\Livewire\Studies\UploadStudies;
use App\Livewire\Users\UserIndex;
use App\Models\Study;
use App\Services\GuestLinkIssuer;
use Illuminate\Support\Facades\Route;

Route::livewire('/guest/{token}', GuestStudy::class)
    ->where('token', '[A-Za-z0-9]{'.GuestLinkIssuer::TOKEN_LENGTH.'}')
    ->middleware('throttle:30,1')
    ->name('guest.show');
Route::get('/guest/{token}/instances/{instance}/file', GuestInstanceFileController::class)
    ->where('token', '[A-Za-z0-9]{'.GuestLinkIssuer::TOKEN_LENGTH.'}')
    ->name('guest.instances.file');
Route::get('/guest/{token}/reports/{report}/pdf', GuestReportPdfController::class)
    ->where('token', '[A-Za-z0-9]{'.GuestLinkIssuer::TOKEN_LENGTH.'}')
    ->middleware('throttle:30,1')
    ->name('guest.reports.pdf');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
    Route::livewire('/register', Register::class)->middleware('throttle:10,1')->name('register');
    Route::livewire('/forgot-password', ForgotPassword::class)->middleware('throttle:10,1')->name('password.request');
    Route::livewire('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/email/verify', VerifyEmail::class)->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/logout', LogoutController::class)->name('logout');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', fn () => redirect()->route('studies.index'))->name('home');

    Route::livewire('/studies', StudyIndex::class)->name('studies.index');
    Route::livewire('/studies/upload', UploadStudies::class)
        ->middleware('can:create,'.Study::class)
        ->name('studies.upload');
    Route::livewire('/studies/{study}', StudyShow::class)->name('studies.show');

    Route::get('/instances/{instance}/file', DicomInstanceFileController::class)->name('instances.file');

    Route::get('/reports/{report}/pdf', ReportPdfController::class)->name('reports.pdf');

    Route::livewire('/profile', EditProfile::class)->name('profile.edit');

    Route::livewire('/requests', PendingShares::class)->name('shares.pending');

    Route::livewire('/package', MyPackage::class)->name('package.show');

    Route::livewire('/admin/packages', PackageIndex::class)
        ->middleware('can:manage-billing')
        ->name('packages.index');
    Route::livewire('/admin/payments', PaymentIndex::class)
        ->middleware('can:manage-billing')
        ->name('payments.index');

    Route::livewire('/users', UserIndex::class)
        ->middleware('can:manage-users')
        ->name('users.index');
});
