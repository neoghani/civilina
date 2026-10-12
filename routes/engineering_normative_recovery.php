<?php

use App\Http\Controllers\NormativeRecoveryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::post(
        'engineering/{organization}/projects/{project}/normative-librarian/recover-references',
        [NormativeRecoveryController::class,'recover']
    )->name('engineering.normative-recovery.references');

    Route::post(
        'engineering/{organization}/projects/{project}/normative-librarian/ocr-queue',
        [NormativeRecoveryController::class,'exportOcr']
    )->name('engineering.normative-recovery.ocr-queue');
});
