<?php

use App\Http\Controllers\EngineeringWorkspaceBridgeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->get(
    'engineering/{organization}/projects/{project}/workspace-safe',
    EngineeringWorkspaceBridgeController::class
)->name('engineering.workspace.bridge');
