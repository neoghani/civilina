<?php

use App\Http\Controllers\EngineeringHomeBridgeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->get(
    'engineering/{organization}/projects/{project}/home',
    EngineeringHomeBridgeController::class
)->name('engineering.home.bridge');
