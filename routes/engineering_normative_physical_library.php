<?php

use App\Http\Controllers\NormativePhysicalLibraryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->post(
    'engineering/{organization}/projects/{project}/normative-librarian/physical-library/build',
    [NormativePhysicalLibraryController::class, 'build']
)->name('engineering.normative-physical-library.build');
