<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

test('minigame rate limits are separated by resource and user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $throttleKey = fn (User $user, string $resource, string $window): string => md5(
        'minigames'.implode(':', ['minigame', $user->id, $resource, $window]),
    );

    RateLimiter::clear($throttleKey($user, 'gold', 'minute'));
    RateLimiter::clear($throttleKey($user, 'gold', 'hour'));
    RateLimiter::clear($throttleKey($user, 'wood', 'minute'));
    RateLimiter::clear($throttleKey($user, 'wood', 'hour'));

    for ($attempt = 0; $attempt < 20; $attempt += 1) {
        RateLimiter::hit($throttleKey($user, 'gold', 'minute'), 60);
    }

    $this->post(route('dashboard.minigames.complete', ['resource' => 'gold']))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('minigame');

    $this->post(route('dashboard.minigames.complete', ['resource' => 'wood']))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);
    RateLimiter::clear($throttleKey($otherUser, 'gold', 'minute'));
    RateLimiter::clear($throttleKey($otherUser, 'gold', 'hour'));

    $this->post(route('dashboard.minigames.complete', ['resource' => 'gold']))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();
});
