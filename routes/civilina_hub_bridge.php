<?php

use App\Http\Controllers\CivilinaHubBridgeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->get(
    'civilina/{organization}/projects/{project}/home',
    CivilinaHubBridgeController::class
)->name('civilina.home.bridge');
