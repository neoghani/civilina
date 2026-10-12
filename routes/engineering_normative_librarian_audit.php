<?php

use App\Http\Controllers\NormativeLibrarianAuditController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->post(
    'engineering/{organization}/projects/{project}/normative-librarian/audit',
    [NormativeLibrarianAuditController::class, 'run']
)->name('engineering.normative-librarian.audit');
