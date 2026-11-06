<?php

use App\Http\Controllers\CommentsPdfController;
use App\Http\Controllers\DicomArchiveChunkController;
use App\Http\Controllers\DicomInstanceFileController;
use App\Http\Controllers\GuestCommentsPdfController;
use App\Http\Controllers\GuestInstanceFileController;
use App\Http\Controllers\GuestReportPdfController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\PatientRecordPdfController;
use App\Http\Controllers\PaymentReceiptFileController;
use App\Http\Controllers\PaymentReceiptPdfController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ReportPdfController;
use App\Http\Controllers\VerifyEmailController;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmail;
use App\Livewire\Billing\MyPackage;
use App\Livewire\Billing\PackageIndex;
use App\Livewire\Billing\PaymentAccountIndex;
use App\Livewire\Billing\PaymentIndex;
use App\Livewire\Guest\GuestStudy;
use App\Livewire\PatientRecords\PatientRecordForm;
use App\Livewire\PatientRecords\PatientRecordIndex;
use App\Livewire\PatientRecords\PatientRecordShow;
use App\Livewire\Profile\EditProfile;
use App\Livewire\Studies\PendingShares;
use App\Livewire\Studies\ShareSenders;
use App\Livewire\Studies\StudyIndex;
use App\Livewire\Studies\StudyShow;
use App\Livewire\Studies\UploadStudies;
use App\Livewire\Users\UserIndex;
use App\Livewire\Users\UserShow;
use App\Models\PatientRecord;
use App\Models\Study;
use App\Services\GuestLinkIssuer;
use Illuminate\Support\Facades\Route;

Route::view('/', 'site.home')->name('home');
Route::get('/pricing', PricingController::class)->name('pricing');
Route::view('/about', 'site.about')->name('about');
Route::view('/contact', 'site.contact')->name('contact');
Route::view('/privacy', 'site.privacy')->name('privacy');
Route::view('/terms', 'site.terms')->name('terms');
Route::view('/payment-receipt', 'site.payment-receipt')->middleware('throttle:30,1')->name('payment-receipt');

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
Route::get('/guest/{token}/comments/pdf', GuestCommentsPdfController::class)
    ->where('token', '[A-Za-z0-9]{'.GuestLinkIssuer::TOKEN_LENGTH.'}')
    ->middleware('throttle:30,1')
    ->name('guest.comments.pdf');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
    Route::livewire('/register', Register::class)->middleware('throttle:10,1')->name('register');
    Route::livewire('/forgot-password', ForgotPassword::class)->middleware('throttle:10,1')->name('password.request');
    Route::livewire('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/email/verify', VerifyEmail::class)->name('verification.notice');
    Route::livewire('/profile', EditProfile::class)->name('profile.edit');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/logout', LogoutController::class)->name('logout');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/studies', StudyIndex::class)->name('studies.index');
    Route::livewire('/studies/upload', UploadStudies::class)
        ->middleware('can:create,'.Study::class)
        ->name('studies.upload');
    Route::livewire('/studies/{study}', StudyShow::class)->name('studies.show');

    Route::get('/instances/{instance}/file', DicomInstanceFileController::class)->name('instances.file');
    Route::put('/archives/{archive}/chunks', DicomArchiveChunkController::class)->name('archives.chunks');

    Route::get('/reports/{report}/pdf', ReportPdfController::class)->name('reports.pdf');

    Route::get('/studies/{study}/comments/pdf', CommentsPdfController::class)->name('studies.comments.pdf');

    Route::livewire('/patients', PatientRecordIndex::class)
        ->middleware('can:viewAny,'.PatientRecord::class)
        ->name('patient-records.index');
    Route::livewire('/patients/create', PatientRecordForm::class)
        ->middleware('can:create,'.PatientRecord::class)
        ->name('patient-records.create');
    Route::livewire('/patients/{patientRecord}', PatientRecordShow::class)->name('patient-records.show');
    Route::livewire('/patients/{patientRecord}/edit', PatientRecordForm::class)->name('patient-records.edit');
    Route::get('/patients/{patientRecord}/pdf', PatientRecordPdfController::class)->name('patient-records.pdf');

    Route::livewire('/requests', PendingShares::class)->name('shares.pending');
    Route::livewire('/senders', ShareSenders::class)
        ->middleware('can:manage-senders')
        ->name('shares.senders');

    Route::livewire('/package', MyPackage::class)->name('package.show');
    Route::get('/package/receipts/{subscription}', PaymentReceiptPdfController::class)->name('package.receipt');

    Route::livewire('/admin/packages', PackageIndex::class)
        ->middleware('can:manage-billing')
        ->name('packages.index');
    Route::livewire('/admin/payments', PaymentIndex::class)
        ->middleware('can:manage-billing')
        ->name('payments.index');
    Route::livewire('/admin/payment-accounts', PaymentAccountIndex::class)
        ->middleware('can:manage-billing')
        ->name('payment-accounts.index');
    Route::get('/admin/payments/{subscription}/receipt', [PaymentReceiptFileController::class, 'subscription'])
        ->middleware('can:manage-billing')
        ->name('payments.receipt-file');
    Route::get('/admin/payment-submissions/{paymentSubmission}/receipt', [PaymentReceiptFileController::class, 'submission'])
        ->middleware('can:manage-billing')
        ->name('payment-submissions.receipt');

    Route::livewire('/users', UserIndex::class)
        ->middleware('can:manage-users')
        ->name('users.index');
    Route::livewire('/users/{user}', UserShow::class)
        ->middleware('can:manage-users')
        ->name('users.show');
});
