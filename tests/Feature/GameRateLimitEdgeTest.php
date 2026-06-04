<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

test('minigame rate limits are separated by resource and user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    RateLimiter::clear('minigame:'.$user->id.':gold:minute');
    RateLimiter::clear('minigame:'.$user->id.':gold:hour');
    RateLimiter::clear('minigame:'.$user->id.':wood:minute');
    RateLimiter::clear('minigame:'.$user->id.':wood:hour');

    for ($attempt = 0; $attempt < 20; $attempt += 1) {
        $this->post(route('dashboard.minigames.complete', ['resource' => 'gold']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();
    }

    $this->post(route('dashboard.minigames.complete', ['resource' => 'gold']))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('minigame');

    $this->post(route('dashboard.minigames.complete', ['resource' => 'wood']))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);
    RateLimiter::clear('minigame:'.$otherUser->id.':gold:minute');
    RateLimiter::clear('minigame:'.$otherUser->id.':gold:hour');

    $this->post(route('dashboard.minigames.complete', ['resource' => 'gold']))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();
});
