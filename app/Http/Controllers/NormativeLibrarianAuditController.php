<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Services\Engineering\NormativeLibrarian\NormativeClassificationAuditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NormativeLibrarianAuditController extends Controller
{
    public function run(
        Request $request,
        Organization $organization,
        Project $project,
        NormativeClassificationAuditor $auditor
    ): RedirectResponse {
        if (isset($project->organization_id)) {
            abort_unless((int)$project->organization_id === (int)$organization->id, 404);
        }

        $validated = $request->validate([
            'limit'=>['nullable','integer','min:1','max:2500'],
            'status'=>['nullable','in:AUTO_CLASSIFIED,TO_REVIEW'],
            'dry_run'=>['nullable','boolean'],
        ]);

        $stats = $auditor->audit(
            (int)($validated['limit'] ?? 500),
            (string)($validated['status'] ?? 'AUTO_CLASSIFIED'),
            ! (bool)($validated['dry_run'] ?? false)
        );

        return back()->with(
            'normative_message',
            'Audit précision : '.json_encode($stats, JSON_UNESCAPED_UNICODE)
        );
    }
}
