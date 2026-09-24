<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('home', 'home')->name('dashboard');
    Route::redirect('dashboard', '/home');

    Route::inertia('overview', 'overview')->name('overview.index');
    Route::inertia('saved', 'saved')->name('saved.index');
    Route::inertia('sales', 'sales')->name('sales.index');
    Route::inertia('quotation', 'quotation')->name('quotation.index');
    Route::inertia('inquiry', 'inquiry')->name('inquiry.index');
    Route::inertia('schedule', 'schedule')->name('schedule.index');
    Route::inertia('message', 'message')->name('message.index');
    Route::inertia('products', 'products')->name('products.index');
    Route::inertia('services', 'services')->name('services.index');
    Route::inertia('accounts', 'accounts')->name('accounts.index');
});

require __DIR__.'/settings.php';
