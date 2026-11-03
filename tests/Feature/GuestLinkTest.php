<?php

use App\Enums\Role;
use App\Livewire\Guest\GuestStudy;
use App\Livewire\Studies\GuestLinks;
use App\Models\DicomInstance;
use App\Models\GuestLink;
use App\Models\Series;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\User;
use App\Services\StudySharing;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('dicom');
});

function guestToken(): string
{
    return Str::random(48);
}

test('physicians can create guest links for studies they own or that were shared with them', function () {
    $physician = User::factory()->physician()->create();
    $shared = Study::factory()->for(User::factory()->technician(), 'uploader')->create();
    StudyShare::factory()->for($shared)->create(['user_id' => $physician->id]);

    expect($physician->can('createGuestLink', Study::factory()->for($physician, 'uploader')->create()))->toBeTrue()
        ->and($physician->can('createGuestLink', $shared))->toBeTrue()
        ->and($physician->can('createGuestLink', Study::factory()->create()))->toBeFalse()
        ->and($physician->can('createGuestLink', StudyShare::factory()->pending()->create(['user_id' => $physician->id])->study))->toBeFalse();
});

test('patients can create guest links only for their own studies', function () {
    $patient = User::factory()->patient()->create();

    expect($patient->can('createGuestLink', Study::factory()->for($patient, 'uploader')->create()))->toBeTrue()
        ->and($patient->can('createGuestLink', Study::factory()->create()))->toBeFalse();
});

test('technicians and administrators cannot create guest links, even for their own studies', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);

    expect($user->can('createGuestLink', Study::factory()->for($user, 'uploader')->create()))->toBeFalse();
})->with([Role::Technician, Role::Admin]);

test('creating a guest link with a PIN shows the link and PIN once and stores only hashes', function () {
    $study = Study::factory()->create();

    $component = Livewire::actingAs($study->uploader)
        ->test(GuestLinks::class, ['study' => $study])
        ->set('requirePin', true)
        ->call('create')
        ->assertSee('Copy it now');

    ['url' => $url, 'pin' => $pin] = $component->get('issued');
    $link = GuestLink::sole();
    $token = Str::afterLast($url, '/');

    expect($pin)->toMatch('/^\d{6}$/')
        ->and($link->token_hash)->toBe(GuestLink::hashToken($token))
        ->and($link->pin_hash)->not->toBe($pin)
        ->and($link->expires_at->diffInHours(now()->addDay(), true))->toBeLessThan(1);

    $component->call('dismissIssued')->assertDontSee($url);
});

test('a guest link without a PIN opens the study and counts the view once per session', function () {
    $series = Series::factory()->create(['modality' => 'XA']);
    $instance = DicomInstance::factory()->for($series)->create();
    $token = guestToken();
    $link = GuestLink::factory()->for($series->study)->withToken($token)->create(['created_by' => $series->study->uploaded_by]);

    $this->get(route('guest.show', $token))
        ->assertOk()
        ->assertSee($series->study->patient_name)
        ->assertSee('Guest view');
    $this->get(route('guest.show', $token))->assertOk();

    Livewire::test(GuestStudy::class, ['token' => $token])
        ->assertViewHas('viewerSeries', fn (array $entries) => $entries[0]['slices'][0]['url'] === route('guest.instances.file', [$token, $instance]));

    expect($link->fresh()->view_count)->toBe(1);
});

test('a guest link with a PIN asks for it before showing the study', function () {
    $token = guestToken();
    $link = GuestLink::factory()->withToken($token)->withPin('123456')->create();

    $this->get(route('guest.show', $token))
        ->assertOk()
        ->assertSee('Enter the PIN')
        ->assertDontSee($link->study->patient_name);

    Livewire::test(GuestStudy::class, ['token' => $token])
        ->set('pin', '654321')
        ->call('unlock')
        ->assertHasErrors(['pin'])
        ->assertSee('That PIN is not correct.')
        ->set('pin', '123456')
        ->call('unlock')
        ->assertHasNoErrors()
        ->assertSee($link->study->patient_name);

    expect($link->fresh()->view_count)->toBe(1);
});

test('PIN attempts are limited per link', function () {
    $token = guestToken();
    GuestLink::factory()->withToken($token)->withPin('123456')->create();
    $component = Livewire::test(GuestStudy::class, ['token' => $token]);

    foreach (range(1, GuestStudy::MAX_PIN_ATTEMPTS) as $attempt) {
        $component->set('pin', '000000')->call('unlock');
    }

    $component->set('pin', '123456')->call('unlock')
        ->assertSee('Too many incorrect attempts.')
        ->assertDontSee('Shared by');
});

test('expired, revoked and unknown links show that the link is unavailable', function (string $state) {
    $token = guestToken();
    $link = match ($state) {
        'expired' => GuestLink::factory()->expired()->withToken($token)->create(),
        'revoked' => GuestLink::factory()->revoked()->withToken($token)->create(),
        'unknown' => GuestLink::factory()->create(),
    };

    $this->get(route('guest.show', $token))
        ->assertOk()
        ->assertSee('This link is no longer available')
        ->assertDontSee($link->study->patient_name);
})->with(['expired', 'revoked', 'unknown']);

test('a link stops working when its creator loses access to the study', function () {
    $study = Study::factory()->create();
    $physician = User::factory()->physician()->create();
    allowSender($physician, $study->uploader);
    app(StudySharing::class)->share($study, $study->uploader, $physician);
    $token = guestToken();
    GuestLink::factory()->for($study)->withToken($token)->create(['created_by' => $physician->id]);

    $this->get(route('guest.show', $token))->assertSee($study->patient_name);

    app(StudySharing::class)->revoke(StudyShare::sole());

    $this->get(route('guest.show', $token))->assertSee('This link is no longer available');
});

test('guests can download the files of the linked study only', function () {
    Storage::disk('dicom')->put('a.dcm', 'dicom-bytes');
    $instance = DicomInstance::factory()->create(['file_path' => 'a.dcm']);
    $other = DicomInstance::factory()->create();
    $token = guestToken();
    GuestLink::factory()->for($instance->series->study)->withToken($token)->create(['created_by' => $instance->series->study->uploaded_by]);

    $response = $this->get(route('guest.instances.file', [$token, $instance]));
    $response->assertOk()->assertHeader('Content-Type', 'application/dicom');
    expect($response->streamedContent())->toBe('dicom-bytes');

    $this->get(route('guest.instances.file', [$token, $other]))->assertNotFound();
    $this->get(route('guest.instances.file', [guestToken(), $instance]))->assertNotFound();
});

test('files of a PIN-protected link need the PIN first', function () {
    Storage::disk('dicom')->put('a.dcm', 'dicom-bytes');
    $instance = DicomInstance::factory()->create(['file_path' => 'a.dcm']);
    $token = guestToken();
    $link = GuestLink::factory()->for($instance->series->study)->withToken($token)->withPin('123456')
        ->create(['created_by' => $instance->series->study->uploaded_by]);

    $this->get(route('guest.instances.file', [$token, $instance]))->assertNotFound();

    $this->withSession(["guest_links.{$link->id}.unlocked" => true])
        ->get(route('guest.instances.file', [$token, $instance]))
        ->assertOk();
});

test('the owner sees and can revoke every link while others see only their own', function () {
    $study = Study::factory()->for(User::factory()->patient(), 'uploader')->create();
    $physician = User::factory()->physician()->create(['name' => 'Carl Cardiologist']);
    StudyShare::factory()->for($study)->create(['user_id' => $physician->id]);
    $ownersLink = GuestLink::factory()->for($study)->create(['created_by' => $study->uploaded_by]);
    $physiciansLink = GuestLink::factory()->for($study)->create(['created_by' => $physician->id]);

    Livewire::actingAs($physician)
        ->test(GuestLinks::class, ['study' => $study])
        ->assertViewHas('links', fn ($links) => $links->modelKeys() === [$physiciansLink->id])
        ->call('revoke', $ownersLink->id)
        ->assertForbidden();

    Livewire::actingAs($study->uploader)
        ->test(GuestLinks::class, ['study' => $study])
        ->assertSee('by Carl Cardiologist')
        ->call('revoke', $physiciansLink->id)
        ->assertSee('Guest link revoked.');

    expect($physiciansLink->fresh()->revoked_at)->not->toBeNull();
});

test('technician owners can revoke links to their studies but not create them', function () {
    $study = Study::factory()->for(User::factory()->technician(), 'uploader')->create();

    Livewire::actingAs($study->uploader)
        ->test(GuestLinks::class, ['study' => $study])
        ->assertOk()
        ->assertDontSee('Create guest link')
        ->call('create')
        ->assertForbidden();
});

test('people who cannot open the study cannot manage its guest links', function () {
    Livewire::actingAs(User::factory()->physician()->create())
        ->test(GuestLinks::class, ['study' => Study::factory()->create()])
        ->assertForbidden();
});

test('old expired links are pruned', function () {
    $old = GuestLink::factory()->create(['expires_at' => now()->subDays(GuestLink::RETENTION_DAYS + 1)]);
    $recent = GuestLink::factory()->expired()->create();

    $this->artisan('model:prune', ['--model' => GuestLink::class])->assertSuccessful();

    $this->assertModelMissing($old);
    $this->assertModelExists($recent);
});
