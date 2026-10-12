<?php

use App\Http\Controllers\Api\EngineeringAnalysisController;
use App\Http\Controllers\Api\EngineeringCheckController;
use App\Http\Controllers\Api\EngineeringDesignBasisController;
use App\Http\Controllers\Api\EngineeringKnowledgeApiController;
use App\Http\Controllers\Api\EngineeringMaterialController;
use App\Http\Controllers\Api\EngineeringProjectController;
use App\Http\Controllers\Api\EngineeringProjectStructureController;
use App\Http\Controllers\Api\EngineeringTechnicalModelController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
])
    ->prefix(
        'organizations/{organization:public_id}/engineering'
    )
    ->group(function (): void {
        Route::get(
            '/projects',
            [
                EngineeringProjectController::class,
                'index',
            ]
        );

        Route::post(
            '/projects',
            [
                EngineeringProjectController::class,
                'store',
            ]
        );

        Route::get(
            '/projects/{project}',
            [
                EngineeringProjectController::class,
                'show',
            ]
        );

        Route::get(
            '/projects/{project}/structure',
            [
                EngineeringProjectStructureController::class,
                'index',
            ]
        );

        Route::post(
            '/projects/{project}/wbs-nodes',
            [
                EngineeringProjectStructureController::class,
                'storeWbsNode',
            ]
        );

        Route::post(
            '/projects/{project}/work-packages',
            [
                EngineeringProjectStructureController::class,
                'storeWorkPackage',
            ]
        );

        Route::post(
            '/projects/{project}/activities',
            [
                EngineeringProjectStructureController::class,
                'storeActivity',
            ]
        );

        Route::post(
            '/projects/{project}/zones',
            [
                EngineeringProjectStructureController::class,
                'storeZone',
            ]
        );

        Route::post(
            '/projects/{project}/ouvrages',
            [
                EngineeringProjectStructureController::class,
                'storeOuvrage',
            ]
        );

        Route::post(
            '/projects/{project}/materials',
            [
                EngineeringMaterialController::class,
                'store',
            ]
        );

        Route::get(
            '/projects/{project}/design-basis',
            [
                EngineeringDesignBasisController::class,
                'index',
            ]
        );

        Route::post(
            '/projects/{project}/design-basis',
            [
                EngineeringDesignBasisController::class,
                'store',
            ]
        );

        Route::post(
            '/projects/{project}/design-basis/{revision}/items',
            [
                EngineeringDesignBasisController::class,
                'addItem',
            ]
        );

        Route::post(
            '/projects/{project}/design-basis/{revision}/review',
            [
                EngineeringDesignBasisController::class,
                'markForReview',
            ]
        );

        Route::post(
            '/projects/{project}/design-basis/{revision}/approve',
            [
                EngineeringDesignBasisController::class,
                'approve',
            ]
        );

        Route::get(
            '/projects/{project}/technical-objects',
            [
                EngineeringTechnicalModelController::class,
                'index',
            ]
        );

        Route::post(
            '/projects/{project}/technical-objects',
            [
                EngineeringTechnicalModelController::class,
                'storeObject',
            ]
        );

        Route::post(
            '/projects/{project}/technical-objects/{object}/revisions',
            [
                EngineeringTechnicalModelController::class,
                'storeRevision',
            ]
        );

        Route::post(
            '/projects/{project}/technical-objects/{object}/revisions/{revision}/review',
            [
                EngineeringTechnicalModelController::class,
                'markRevisionForReview',
            ]
        );

        Route::post(
            '/projects/{project}/technical-objects/{object}/revisions/{revision}/approve',
            [
                EngineeringTechnicalModelController::class,
                'approveRevision',
            ]
        );

        Route::get(
            '/knowledge/standards',
            [
                EngineeringKnowledgeApiController::class,
                'standards',
            ]
        );

        Route::get(
            '/knowledge/requirements',
            [
                EngineeringKnowledgeApiController::class,
                'requirements',
            ]
        );

        Route::get(
            '/projects/{project}/standard-profiles',
            [
                EngineeringKnowledgeApiController::class,
                'projectProfiles',
            ]
        );

        Route::get(
            '/projects/{project}/analysis-models',
            [
                EngineeringAnalysisController::class,
                'index',
            ]
        );

        Route::post(
            '/projects/{project}/analysis-models',
            [
                EngineeringAnalysisController::class,
                'store',
            ]
        );

        Route::post(
            '/projects/{project}/analysis-models/{analysisModel}/items',
            [
                EngineeringAnalysisController::class,
                'addItem',
            ]
        );

        Route::post(
            '/projects/{project}/analysis-models/{analysisModel}/review',
            [
                EngineeringAnalysisController::class,
                'markForReview',
            ]
        );

        Route::post(
            '/projects/{project}/analysis-models/{analysisModel}/approve',
            [
                EngineeringAnalysisController::class,
                'approve',
            ]
        );

        Route::post(
            '/projects/{project}/analysis-models/{analysisModel}/runs',
            [
                EngineeringAnalysisController::class,
                'prepareRun',
            ]
        );

        Route::get(
            '/projects/{project}/checks',
            [
                EngineeringCheckController::class,
                'index',
            ]
        );

        Route::get(
            '/projects/{project}/checks/{check}',
            [
                EngineeringCheckController::class,
                'show',
            ]
        );
    });
