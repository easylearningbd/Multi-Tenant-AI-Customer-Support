<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController as AdminAuthenticatedSessionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ProfileController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::bind('subscriber', fn (string $value): User => User::query()
    ->subscribers()
    ->whereKey($value)
    ->firstOrFail());

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'role:user'])->name('dashboard');

Route::middleware(['auth', 'role:user'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AdminAuthenticatedSessionController::class, 'create'])
            ->name('login');
        Route::post('login', [AdminAuthenticatedSessionController::class, 'store'])
            ->name('login.store');
    });

    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('profile', [AdminProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [AdminProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [AdminProfileController::class, 'updatePassword'])->name('profile.password.update');
        Route::delete('profile/avatar', [AdminProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/{subscriber}', [AdminUserController::class, 'show'])->whereNumber('subscriber')->name('users.show');
        Route::get('users/{subscriber}/edit', [AdminUserController::class, 'edit'])->whereNumber('subscriber')->name('users.edit');
        Route::put('users/{subscriber}', [AdminUserController::class, 'update'])->whereNumber('subscriber')->name('users.update');
        Route::delete('users/{subscriber}', [AdminUserController::class, 'destroy'])->whereNumber('subscriber')->name('users.destroy');

        Route::post('logout', [AdminAuthenticatedSessionController::class, 'destroy'])
            ->name('logout');
    });
});

require __DIR__.'/auth.php';
