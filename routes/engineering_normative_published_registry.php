<?php

use App\Http\Controllers\EngineeringNormativePublishedRegistryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->get(
    'engineering/{organization}/projects/{project}/normative-registry',
    [EngineeringNormativePublishedRegistryController::class, 'index']
)->name('engineering.normative-published-registry.index');
