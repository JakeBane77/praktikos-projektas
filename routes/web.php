<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('dashboard/collect', [DashboardController::class, 'collect'])->name('dashboard.collect');
    Route::post('dashboard/buildings/{building}/upgrade', [DashboardController::class, 'upgrade'])->name('dashboard.buildings.upgrade');
});

require __DIR__.'/settings.php';
