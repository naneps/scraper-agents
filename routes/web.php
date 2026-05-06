<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('sources/tester', [\App\Http\Controllers\Admin\SourceController::class, 'tester'])->name('sources.tester');
        Route::post('sources/tester/run', [\App\Http\Controllers\Admin\SourceController::class, 'runTester'])->name('sources.tester.run');
        Route::post('sources/verify', [\App\Http\Controllers\Admin\SourceController::class, 'verify'])->name('sources.verify');
        Route::resource('sources', \App\Http\Controllers\Admin\SourceController::class)->except(['show']);
        Route::resource('categories', \App\Http\Controllers\Admin\CategoryController::class)->except(['show']);
    });
});

require __DIR__.'/settings.php';
