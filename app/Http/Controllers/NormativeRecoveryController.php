<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Services\Engineering\NormativeLibrarian\NormativeOcrQueueExporter;
use App\Services\Engineering\NormativeLibrarian\NormativeReferenceRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NormativeRecoveryController extends Controller
{
    public function recover(
        Request $request,
        Organization $organization,
        Project $project,
        NormativeReferenceRecoveryService $service
    ): RedirectResponse {
        $this->guardProject($organization,$project);

        $validated = $request->validate([
            'limit'=>['nullable','integer','min:1','max:2500'],
        ]);

        $stats = $service->run(
            isset($validated['limit']) ? (int)$validated['limit'] : null
        );

        return back()->with(
            'normative_message',
            'Récupération références : '.json_encode($stats,JSON_UNESCAPED_UNICODE)
        );
    }

    public function exportOcr(
        Organization $organization,
        Project $project,
        NormativeOcrQueueExporter $exporter
    ): RedirectResponse {
        $this->guardProject($organization,$project);

        $result = $exporter->export();

        return back()->with(
            'normative_message',
            'File OCR : '.json_encode($result,JSON_UNESCAPED_UNICODE)
        );
    }

    private function guardProject(Organization $organization, Project $project): void
    {
        if (isset($project->organization_id)) {
            abort_unless(
                (int)$project->organization_id === (int)$organization->id,
                404
            );
        }
    }
}
