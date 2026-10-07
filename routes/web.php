<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceChargeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TenancyController;
use App\Http\Controllers\Tenant\MyInvoiceController;
use App\Http\Controllers\Tenant\MyMaintenanceController;
use App\Http\Controllers\Tenant\OnlinePaymentController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UtilityBillController;
use App\Http\Controllers\UtilityMeterController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Landlord area
    Route::middleware('role:landlord')->group(function () {
        Route::resource('properties', PropertyController::class);
        Route::resource('properties.units', UnitController::class)->shallow()->only(['store', 'update', 'destroy']);
        Route::resource('tenants', TenantController::class)->only(['index', 'store', 'show']);
        Route::get('tenancies/create', [TenancyController::class, 'create'])->name('tenancies.create');
        Route::post('tenancies', [TenancyController::class, 'store'])->name('tenancies.store');
        Route::post('tenancies/{tenancy}/end', [TenancyController::class, 'end'])->name('tenancies.end');
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.payments.store');
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        Route::post('invoices/{invoice}/items', [InvoiceChargeController::class, 'store'])->name('invoices.items.store');
        Route::delete('invoices/{invoice}/items/{item}', [InvoiceChargeController::class, 'destroy'])->name('invoices.items.destroy');
        Route::post('invoices/{invoice}/scan-bill', [InvoiceChargeController::class, 'scan'])->name('invoices.scan');
        Route::delete('invoices/{invoice}/scan-bill', [InvoiceChargeController::class, 'dismiss'])->name('invoices.scan.dismiss');
        Route::get('maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
        Route::post('maintenance/{ticket}/assign', [MaintenanceController::class, 'assign'])->name('maintenance.assign');
        Route::post('staff', [MaintenanceController::class, 'storeStaff'])->name('staff.store');
        Route::post('properties/{property}/meters', [UtilityMeterController::class, 'store'])->name('meters.store');
        Route::put('meters/{meter}', [UtilityMeterController::class, 'update'])->name('meters.update');
        Route::delete('meters/{meter}', [UtilityMeterController::class, 'destroy'])->name('meters.destroy');
        Route::post('meters/{meter}/bills', [UtilityBillController::class, 'store'])->name('meters.bills.store');
        Route::post('meters/{meter}/scan', [UtilityBillController::class, 'scan'])->name('meters.scan');
        Route::delete('meters/{meter}/scan', [UtilityBillController::class, 'dismiss'])->name('meters.scan.dismiss');
        Route::delete('utility-bills/{bill}', [UtilityBillController::class, 'destroy'])->name('utility-bills.destroy');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    // Landlord or tenant (policy decides)
    // Shared ticket page: landlord, tenant who reported it, assigned staff (policy decides)
    Route::get('maintenance/{ticket}', [MaintenanceController::class, 'show'])->name('maintenance.show');
    Route::post('maintenance/{ticket}/status', [MaintenanceController::class, 'updateStatus'])->name('maintenance.status');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

    Route::get('payments/toyyibpay/return', [OnlinePaymentController::class, 'handleReturn'])->name('payments.toyyibpay.return');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('tenancies/{tenancy}/agreement', [TenancyController::class, 'agreement'])->name('tenancies.agreement');

    // Tenant area
    Route::middleware('role:tenant')->prefix('my')->name('my.')->group(function () {
        Route::inertia('unit', 'coming-soon', ['title' => 'My Unit'])->name('unit');
        Route::get('invoices', [MyInvoiceController::class, 'index'])->name('invoices');
        Route::get('invoices/{invoice}', [MyInvoiceController::class, 'show'])->name('invoices.show');
        Route::post('invoices/{invoice}/pay', [OnlinePaymentController::class, 'pay'])->name('invoices.pay');
        Route::get('maintenance', [MyMaintenanceController::class, 'index'])->name('maintenance');
        Route::post('maintenance', [MyMaintenanceController::class, 'store'])->name('maintenance.store');
    });

    // Maintenance staff area
    Route::middleware('role:maintenance')->group(function () {
        Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    });
});

// ToyyibPay server callback: no login, no CSRF (verified by hash instead)
Route::post('payments/toyyibpay/callback', [OnlinePaymentController::class, 'callback'])->name('payments.toyyibpay.callback');

require __DIR__.'/settings.php';
