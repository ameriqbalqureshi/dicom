<?php

use App\Http\Controllers\DicomInstanceFileController;
use App\Http\Controllers\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Studies\StudyIndex;
use App\Livewire\Studies\StudyShow;
use App\Livewire\Studies\UploadStudies;
use App\Livewire\Users\UserIndex;
use App\Models\Study;
use Illuminate\Support\Facades\Route;

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

    Route::livewire('/users', UserIndex::class)
        ->middleware('can:manage-users')
        ->name('users.index');

    Route::post('/logout', LogoutController::class)->name('logout');
});
