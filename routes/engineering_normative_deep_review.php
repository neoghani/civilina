<?php
use App\Http\Controllers\EngineeringNormativeDeepReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('engineering/{organization}/projects/{project}/normative-deep-review')
    ->name('engineering.normative-deep-review.')
    ->group(function(){
        Route::get('/',[EngineeringNormativeDeepReviewController::class,'index'])->name('index');
        Route::get('/{candidate}',[EngineeringNormativeDeepReviewController::class,'show'])->whereNumber('candidate')->name('show');
        Route::post('/{candidate}/decision',[EngineeringNormativeDeepReviewController::class,'decide'])->whereNumber('candidate')->name('decision');
    });
