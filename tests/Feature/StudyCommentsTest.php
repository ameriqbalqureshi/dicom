<?php

use App\Livewire\Studies\StudyComments;
use App\Models\GuestLink;
use App\Models\Study;
use App\Models\StudyComment;
use App\Models\StudyShare;
use App\Models\User;
use App\Services\UserDeleter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('dicom');
});

/**
 * A study uploaded by a technician and shared with a physician.
 *
 * @return array{0: Study, 1: User}
 */
function commentedStudyWithPhysician(): array
{
    $study = Study::factory()->for(User::factory()->technician(), 'uploader')->create();
    $physician = User::factory()->physician()->create();
    StudyShare::factory()->for($study)->create(['user_id' => $physician->id]);

    return [$study, $physician];
}

test('anyone who can open a study can comment on it, and everyone with access sees the comment', function () {
    [$study, $physician] = commentedStudyWithPhysician();

    Livewire::actingAs($physician)->test(StudyComments::class, ['study' => $study])
        ->set('body', 'Proximal LAD looks tight')
        ->call('addComment')
        ->assertHasNoErrors()
        ->assertSet('body', '');

    $comment = StudyComment::sole();
    expect($comment->body)->toBe('Proximal LAD looks tight')
        ->and($comment->author_id)->toBe($physician->id)
        ->and($comment->study_id)->toBe($study->id);

    Livewire::actingAs($study->uploader)->test(StudyComments::class, ['study' => $study])
        ->assertSee('Proximal LAD looks tight')
        ->assertSee($physician->name);
});

test('a comment cannot be empty', function () {
    [$study, $physician] = commentedStudyWithPhysician();

    Livewire::actingAs($physician)->test(StudyComments::class, ['study' => $study])
        ->call('addComment')
        ->assertHasErrors(['body' => 'required']);

    expect(StudyComment::count())->toBe(0);
});

test('users who cannot open a study cannot see or add comments', function () {
    $study = Study::factory()->create();
    $pending = StudyShare::factory()->pending()->for($study)->create();

    Livewire::actingAs(User::factory()->physician()->create())->test(StudyComments::class, ['study' => $study])->assertForbidden();
    Livewire::actingAs($pending->recipient)->test(StudyComments::class, ['study' => $study])->assertForbidden();
});

test('authors can edit their own comments but nobody else can, not even the owner', function () {
    [$study, $physician] = commentedStudyWithPhysician();
    $comment = StudyComment::factory()->for($study)->create(['author_id' => $physician->id, 'body' => 'First thoughts']);

    Livewire::actingAs($study->uploader)->test(StudyComments::class, ['study' => $study])
        ->assertDontSee('Edit')
        ->call('startEditing', $comment->id)
        ->assertForbidden();

    $this->travel(1)->minute();

    Livewire::actingAs($physician)->test(StudyComments::class, ['study' => $study])
        ->call('startEditing', $comment->id)
        ->assertSet('editingBody', 'First thoughts')
        ->set('editingBody', 'Second thoughts')
        ->call('saveEdit')
        ->assertHasNoErrors()
        ->assertSee('Second thoughts')
        ->assertSee('edited');

    expect($comment->fresh()->body)->toBe('Second thoughts');
});

test('authors and the study owner can delete a comment, but other viewers cannot', function () {
    [$study, $physician] = commentedStudyWithPhysician();
    $colleague = User::factory()->physician()->create();
    StudyShare::factory()->for($study)->create(['user_id' => $colleague->id]);
    $byPhysician = StudyComment::factory()->for($study)->create(['author_id' => $physician->id]);
    $byColleague = StudyComment::factory()->for($study)->create(['author_id' => $colleague->id]);

    Livewire::actingAs($colleague)->test(StudyComments::class, ['study' => $study])
        ->call('deleteComment', $byPhysician->id)
        ->assertForbidden();

    Livewire::actingAs($colleague)->test(StudyComments::class, ['study' => $study])
        ->call('deleteComment', $byColleague->id);

    Livewire::actingAs($study->uploader)->test(StudyComments::class, ['study' => $study])
        ->call('deleteComment', $byPhysician->id);

    expect(StudyComment::count())->toBe(0);
});

test('comments on other studies cannot be changed through this study', function () {
    [$study, $physician] = commentedStudyWithPhysician();
    $elsewhere = StudyComment::factory()->create(['author_id' => $physician->id]);

    Livewire::actingAs($physician)->test(StudyComments::class, ['study' => $study])
        ->call('deleteComment', $elsewhere->id)
        ->assertNotFound();

    expect($elsewhere->fresh())->not->toBeNull();
});

test('comments are kept without an author when the author is deleted', function () {
    [$study, $physician] = commentedStudyWithPhysician();
    StudyComment::factory()->for($study)->create(['author_id' => $physician->id, 'body' => 'Still relevant']);

    app(UserDeleter::class)->delete($physician);

    Livewire::actingAs($study->uploader)->test(StudyComments::class, ['study' => $study])
        ->assertSee('Still relevant')
        ->assertSee('Deleted user');
});

test('the comments PDF can be downloaded by anyone who can open the study', function () {
    [$study, $physician] = commentedStudyWithPhysician();
    StudyComment::factory()->for($study)->create(['author_id' => $physician->id]);

    foreach ([$physician, $study->uploader] as $user) {
        $this->actingAs($user)->get(route('studies.comments.pdf', $study))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    $this->actingAs(User::factory()->physician()->create())->get(route('studies.comments.pdf', $study))->assertForbidden();
});

test('guests can read comments and download them as a PDF, but cannot post', function () {
    [$study, $physician] = commentedStudyWithPhysician();
    StudyComment::factory()->for($study)->create(['author_id' => $physician->id, 'body' => 'Visible to guests']);
    $token = Str::random(48);
    GuestLink::factory()->for($study)->withToken($token)->create(['created_by' => $physician->id]);

    $this->get(route('guest.show', $token))
        ->assertOk()
        ->assertSee('Visible to guests')
        ->assertDontSee('Add a comment');

    $this->get(route('guest.comments.pdf', $token))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('guest.comments.pdf', Str::random(48)))->assertNotFound();
});

test('guests must enter the PIN before reading comments', function () {
    [$study, $physician] = commentedStudyWithPhysician();
    StudyComment::factory()->for($study)->create(['author_id' => $physician->id, 'body' => 'Behind the PIN']);
    $token = Str::random(48);
    GuestLink::factory()->for($study)->withToken($token)->withPin('123456')->create(['created_by' => $physician->id]);

    $this->get(route('guest.show', $token))->assertOk()->assertDontSee('Behind the PIN');
    $this->get(route('guest.comments.pdf', $token))->assertNotFound();
});
