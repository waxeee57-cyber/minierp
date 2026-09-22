<?php

use Illuminate\Support\Facades\Route;
use Modules\Invoicing\Http\Controllers\InvoiceController;

Route::prefix('invoicing')->name('invoicing.')->group(function () {
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('invoices/{invoice}/xml', [InvoiceController::class, 'xml'])->name('invoices.xml');
});
