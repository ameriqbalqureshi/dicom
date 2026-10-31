<?php

use App\Http\Controllers\DicomInstanceFileController;
use App\Http\Controllers\GuestInstanceFileController;
use App\Http\Controllers\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Guest\GuestStudy;
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

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('studies.index'))->name('home');

    Route::livewire('/studies', StudyIndex::class)->name('studies.index');
    Route::livewire('/studies/upload', UploadStudies::class)
        ->middleware('can:create,'.Study::class)
        ->name('studies.upload');
    Route::livewire('/studies/{study}', StudyShow::class)->name('studies.show');

    Route::get('/instances/{instance}/file', DicomInstanceFileController::class)->name('instances.file');

    Route::livewire('/requests', PendingShares::class)->name('shares.pending');

    Route::livewire('/users', UserIndex::class)
        ->middleware('can:manage-users')
        ->name('users.index');

    Route::post('/logout', LogoutController::class)->name('logout');
});
