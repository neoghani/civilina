<?php

use App\Http\Controllers\NormativeCanonicalReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get(
        'engineering/{organization}/projects/{project}/normative-librarian/canonical-review',
        [NormativeCanonicalReviewController::class,'index']
    )->name('engineering.normative-canonical-review.index');

    Route::patch(
        'engineering/{organization}/projects/{project}/normative-librarian/canonical-review/{document}/validate',
        [NormativeCanonicalReviewController::class,'validateGroup']
    )->name('engineering.normative-canonical-review.validate');
});
