<?php

use App\Http\Controllers\RedirectController;
use App\Http\Controllers\ViewController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [RedirectController::class, 'dashboard'])->name('dashboard');
    Route::get('home', [ViewController::class, 'home'])->name('home.index');
    Route::get('home/{stone}', [ViewController::class, 'stoneProfile'])->name('home.show');

    Route::get('overview', [ViewController::class, 'overview'])->name('overview.index');
    Route::get('saved', [ViewController::class, 'saved'])->name('saved.index');
    Route::get('saved/{stone}', [ViewController::class, 'stoneProfile'])->name('saved.show');
    Route::get('sales', [ViewController::class, 'sales'])->name('sales.index');
    Route::get('quotation', [ViewController::class, 'quotation'])->name('quotation.index');
    Route::get('inquiry', [ViewController::class, 'inquiry'])->name('inquiry.index');
    Route::get('schedule', [ViewController::class, 'schedule'])->name('schedule.index');
    Route::get('message', [ViewController::class, 'message'])->name('message.index');
    Route::get('stones', [ViewController::class, 'stones'])->name('stones.index');
    Route::get('services', [ViewController::class, 'services'])->name('services.index');
    Route::get('accounts', [ViewController::class, 'accounts'])->name('accounts.index');
});

require __DIR__.'/settings.php';
