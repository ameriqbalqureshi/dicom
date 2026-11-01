<?php

use App\Http\Middleware\RecordLastSeen;
use App\Models\User;

test('using the service records when the user was last seen without changing updated_at', function () {
    $this->freezeSecond();
    $user = User::factory()->physician()->create(['last_seen_at' => null, 'updated_at' => now()->subWeek()]);

    $this->actingAs($user)->get(route('studies.index'))->assertOk();

    $user->refresh();
    expect($user->last_seen_at->equalTo(now()))->toBeTrue()
        ->and($user->updated_at->equalTo(now()->subWeek()))->toBeTrue();
});

test('the last-seen time is only updated once it is a few minutes old', function () {
    $this->freezeSecond();
    $recently = now()->subMinutes(RecordLastSeen::RESOLUTION_MINUTES - 1);
    $longAgo = now()->subMinutes(RecordLastSeen::RESOLUTION_MINUTES + 1);
    $fresh = User::factory()->physician()->create(['last_seen_at' => $recently]);
    $stale = User::factory()->physician()->create(['last_seen_at' => $longAgo]);

    $this->actingAs($fresh)->get(route('studies.index'));
    $this->actingAs($stale)->get(route('studies.index'));

    expect($fresh->refresh()->last_seen_at->equalTo($recently))->toBeTrue()
        ->and($stale->refresh()->last_seen_at->equalTo(now()))->toBeTrue();
});
