<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Engineering\EngineeringTenantAccessService;
use App\Services\Engineering\TechnicalModelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngineeringMaterialController extends Controller
{
    public function store(
        Request $request,
        Organization $organization,
        string $project,
        EngineeringTenantAccessService $access,
        TechnicalModelService $technical
    ): JsonResponse {
        $access->authorizeEdit(
            $request->user(),
            $organization
        );

        $projectModel =
            $access->scopedProject(
                $organization,
                $project
            );

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:100',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'material_type' => [
                'required',
                'string',
                'max:100',
            ],
            'standard_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'properties' => [
                'sometimes',
                'array',
            ],
        ]);

        $material = $technical->createMaterial(
            $projectModel,
            $validated['code'],
            $validated['name'],
            $validated['material_type'],
            $validated['standard_reference'] ?? null,
            $validated['properties'] ?? []
        );

        return response()->json([
            'data' => $material,
        ], 201);
    }
}
