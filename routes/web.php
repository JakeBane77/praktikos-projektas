<?php

use App\Http\Controllers\AllianceChatController;
use App\Http\Controllers\AllianceController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('immersive', [DashboardController::class, 'immersive'])->name('immersive');
    Route::post('dashboard/collect', [DashboardController::class, 'collect'])->name('dashboard.collect');
    Route::post('dashboard/minigames/{resource}/complete', [DashboardController::class, 'completeMinigame'])
        ->middleware('throttle:minigames')
        ->name('dashboard.minigames.complete');
    Route::post('dashboard/prestige', [DashboardController::class, 'prestige'])->name('dashboard.prestige');
    Route::post('dashboard/buildings/{building}/upgrade', [DashboardController::class, 'upgrade'])->name('dashboard.buildings.upgrade');
    Route::post('dashboard/achievements/unlocks/seen', [DashboardController::class, 'markAchievementUnlocksSeen'])
        ->name('dashboard.achievements.unlocks.seen');
    Route::post('dashboard/weather-location', [DashboardController::class, 'updateWeatherLocation'])
        ->name('dashboard.weather-location');
    Route::post('dashboard/weather-location/default', [DashboardController::class, 'resetWeatherLocation'])
        ->name('dashboard.weather-location.default');

    Route::post('alliances', [AllianceController::class, 'store'])->name('alliances.store');
    Route::patch('alliances/{alliance}', [AllianceController::class, 'update'])->name('alliances.update');
    Route::post('alliances/{alliance}/join', [AllianceController::class, 'join'])->name('alliances.join');
    Route::post('alliances/{alliance}/apply', [AllianceController::class, 'apply'])->name('alliances.apply');
    Route::delete('alliances/{alliance}/leave', [AllianceController::class, 'leave'])->name('alliances.leave');
    Route::patch('alliances/{alliance}/applications/{application}/accept', [AllianceController::class, 'acceptApplication'])->name('alliances.applications.accept');
    Route::delete('alliances/{alliance}/applications/{application}', [AllianceController::class, 'denyApplication'])->name('alliances.applications.deny');
    Route::patch('alliances/{alliance}/members/{membership}/promote', [AllianceController::class, 'promote'])->name('alliances.members.promote');
    Route::patch('alliances/{alliance}/members/{membership}/demote', [AllianceController::class, 'demote'])->name('alliances.members.demote');
    Route::patch('alliances/{alliance}/members/{membership}/transfer-leadership', [AllianceController::class, 'transferLeadership'])->name('alliances.members.transfer-leadership');
    Route::delete('alliances/{alliance}/members/{membership}', [AllianceController::class, 'kick'])->name('alliances.members.kick');
    Route::delete('alliances/{alliance}', [AllianceController::class, 'destroy'])->name('alliances.destroy');
    Route::post('alliances/{alliance}/chat-messages', [AllianceChatController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('alliances.chat-messages.store');
    Route::post('alliance-goals/{goal}/contribute', [AllianceController::class, 'contribute'])
        ->name('alliance-goals.contribute');
});

require __DIR__.'/settings.php';
