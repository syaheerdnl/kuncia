<?php

use App\Http\Controllers\PropertyController;
use App\Http\Controllers\TenancyController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Landlord area — placeholders are swapped for real controllers module by module.
    Route::middleware('role:landlord')->group(function () {
        Route::resource('properties', PropertyController::class);
        Route::resource('properties.units', UnitController::class)->shallow()->only(['store', 'update', 'destroy']);
        Route::resource('tenants', TenantController::class)->only(['index', 'store', 'show']);
        Route::get('tenancies/create', [TenancyController::class, 'create'])->name('tenancies.create');
        Route::post('tenancies', [TenancyController::class, 'store'])->name('tenancies.store');
        Route::post('tenancies/{tenancy}/end', [TenancyController::class, 'end'])->name('tenancies.end');
        Route::inertia('invoices', 'coming-soon', ['title' => 'Invoices'])->name('invoices.index');
        Route::inertia('maintenance', 'coming-soon', ['title' => 'Maintenance'])->name('maintenance.index');
        Route::inertia('reports', 'coming-soon', ['title' => 'Reports'])->name('reports.index');
    });

    // Landlord or tenant (policy decides)
    Route::get('tenancies/{tenancy}/agreement', [TenancyController::class, 'agreement'])->name('tenancies.agreement');

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
