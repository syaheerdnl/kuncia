<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Landlord area — placeholders are swapped for real controllers module by module.
    Route::middleware('role:landlord')->group(function () {
        Route::inertia('properties', 'coming-soon', ['title' => 'Properties'])->name('properties.index');
        Route::inertia('tenants', 'coming-soon', ['title' => 'Tenants'])->name('tenants.index');
        Route::inertia('invoices', 'coming-soon', ['title' => 'Invoices'])->name('invoices.index');
        Route::inertia('maintenance', 'coming-soon', ['title' => 'Maintenance'])->name('maintenance.index');
        Route::inertia('reports', 'coming-soon', ['title' => 'Reports'])->name('reports.index');
    });

    // Tenant area
    Route::middleware('role:tenant')->prefix('my')->name('my.')->group(function () {
        Route::inertia('unit', 'coming-soon', ['title' => 'My Unit'])->name('unit');
        Route::inertia('invoices', 'coming-soon', ['title' => 'My Invoices'])->name('invoices');
        Route::inertia('maintenance', 'coming-soon', ['title' => 'My Requests'])->name('maintenance');
    });

    // Maintenance staff area
    Route::middleware('role:maintenance')->group(function () {
        Route::inertia('tasks', 'coming-soon', ['title' => 'My Tasks'])->name('tasks.index');
    });
});

require __DIR__.'/settings.php';
