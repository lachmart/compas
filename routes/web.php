<?php

use App\Http\Controllers\Admin\GameController;
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
});

require __DIR__.'/auth.php';
