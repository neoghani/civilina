<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Services\Engineering\NormativeLibrarian\NormativePhysicalLibraryOrganizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NormativePhysicalLibraryController extends Controller
{
    public function build(
        Request $request,
        Organization $organization,
        Project $project,
        NormativePhysicalLibraryOrganizer $organizer
    ): RedirectResponse {
        if (isset($project->organization_id)) {
            abort_unless((int)$project->organization_id === (int)$organization->id, 404);
        }

        $validated = $request->validate([
            'limit'=>['nullable','integer','min:1','max:500'],
        ]);

        $stats = $organizer->build(
            null,
            null,
            false,
            (int)($validated['limit'] ?? 250)
        );

        return back()->with(
            'normative_message',
            'Dossier physique mis à jour : '.json_encode($stats, JSON_UNESCAPED_UNICODE)
        );
    }
}
