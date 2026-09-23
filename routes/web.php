<?php

use App\Http\Controllers\Admin\GameController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\TrackingController;
use App\Http\Controllers\Play\AttemptController;
use App\Http\Controllers\Play\PlayController;
use App\Http\Controllers\Play\PositionPingController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('games', [GameController::class, 'index'])->name('games.index');
    Route::get('games/create', [GameController::class, 'create'])->name('games.create');
    Route::post('games', [GameController::class, 'store'])->name('games.store');
    Route::get('games/{game}/edit', [GameController::class, 'edit'])->name('games.edit');
    Route::put('games/{game}', [GameController::class, 'update'])->name('games.update');
    Route::delete('games/{game}', [GameController::class, 'destroy'])->name('games.destroy');
    Route::patch('games/{game}/publish', [GameController::class, 'publish'])->name('games.publish');
    Route::patch('games/{game}/unpublish', [GameController::class, 'unpublish'])->name('games.unpublish');

    Route::post('games/{game}/locations', [LocationController::class, 'store'])->name('games.locations.store');
    Route::patch('games/{game}/locations/reorder', [LocationController::class, 'reorder'])->name('games.locations.reorder');

    Route::get('games/{game}/tracking', [TrackingController::class, 'show'])->name('games.tracking');

    Route::scopeBindings()->group(function () {
        Route::put('games/{game}/locations/{location}', [LocationController::class, 'update'])->name('games.locations.update');
        Route::delete('games/{game}/locations/{location}', [LocationController::class, 'destroy'])->name('games.locations.destroy');
        Route::get('games/{game}/players/{playerSession}', [TrackingController::class, 'player'])->name('games.players.show');
        Route::patch('games/{game}/players/{playerSession}/attempts/{attempt}/flag', [TrackingController::class, 'flag'])->name('games.players.attempts.flag');
    });
});

Route::prefix('hra/{game:slug}')->middleware('published.game')->group(function () {
    Route::get('/', [PlayController::class, 'show'])->name('play.show');
    Route::post('/join', [PlayController::class, 'join'])->name('play.join');

    Route::scopeBindings()->group(function () {
        Route::post('/locations/{location}/arrive', [AttemptController::class, 'arrive'])->name('play.attempts.arrive');
        Route::post('/locations/{location}/answer', [AttemptController::class, 'answer'])->name('play.attempts.answer');
        Route::post('/locations/{location}/photo', [AttemptController::class, 'photo'])->name('play.attempts.photo');
    });

    Route::post('/pings', [PositionPingController::class, 'store'])->name('play.pings.store');
});

require __DIR__.'/auth.php';
