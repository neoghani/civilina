<?php

use App\Http\Controllers\NormativeLibrarianController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get(
        'engineering/{organization}/projects/{project}/normative-librarian',
        [NormativeLibrarianController::class, 'index']
    )->name('engineering.normative-librarian.index');

    Route::post(
        'engineering/{organization}/projects/{project}/normative-librarian/scan',
        [NormativeLibrarianController::class, 'scan']
    )->name('engineering.normative-librarian.scan');

    Route::post(
        'engineering/{organization}/projects/{project}/normative-librarian/refine',
        [NormativeLibrarianController::class, 'refine']
    )->name('engineering.normative-librarian.refine');

    Route::post(
        'engineering/{organization}/projects/{project}/normative-librarian/publish',
        [NormativeLibrarianController::class, 'publish']
    )->name('engineering.normative-librarian.publish');

    Route::post(
        'engineering/{organization}/projects/{project}/normative-librarian/export',
        [NormativeLibrarianController::class, 'export']
    )->name('engineering.normative-librarian.export');

    Route::get(
        'engineering/{organization}/projects/{project}/normative-librarian/{document}',
        [NormativeLibrarianController::class, 'show']
    )->name('engineering.normative-librarian.show');

    Route::get(
        'engineering/{organization}/projects/{project}/normative-librarian/{document}/inline',
        [NormativeLibrarianController::class, 'inline']
    )->name('engineering.normative-librarian.inline');

    Route::patch(
        'engineering/{organization}/projects/{project}/normative-librarian/{document}',
        [NormativeLibrarianController::class, 'update']
    )->name('engineering.normative-librarian.update');
});
