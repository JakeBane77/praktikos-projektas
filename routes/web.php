<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::inertia('immersive', 'Immersive')->name('immersive');
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
});

require __DIR__.'/settings.php';
