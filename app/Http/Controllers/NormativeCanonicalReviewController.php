<?php

namespace App\Http\Controllers;

use App\Models\NormativeLibrarianDocument;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NormativeCanonicalReviewController extends Controller
{
    public function index(
        Request $request,
        Organization $organization,
        Project $project
    ): View {
        $this->guardProject($organization,$project);

        $status = trim((string)$request->query('status',''));
        $q = trim((string)$request->query('q',''));

        $query = NormativeLibrarianDocument::query()
            ->whereNotNull('canonical_group_key')
            ->whereNotNull('edition_key')
            ->where(function($sub) {
                $sub->whereColumn('id','canonical_document_id')
                    ->orWhere('version_status','CANONICAL');
            })
            ->orderByRaw("case
                when consensus_status = 'CONFLICT' then 4
                when consensus_status = 'INSUFFICIENT' then 3
                when classification_status = 'TO_REVIEW' then 2
                when audit_status = 'SAFE' then 0
                else 1 end desc")
            ->orderBy('reference_code');

        if ($status !== '') {
            if ($status === 'SAFE') {
                $query->where('audit_status','SAFE');
            } else {
                $query->where('consensus_status',$status);
            }
        }

        if ($q !== '') {
            $query->where(function($sub) use ($q) {
                $sub->where('reference_code','like',"%{$q}%")
                    ->orWhere('title','like',"%{$q}%")
                    ->orWhere('domain','like',"%{$q}%")
                    ->orWhere('subdomain','like',"%{$q}%");
            });
        }

        $groups = $query->limit(800)->get();

        $counts = [
            'safe'=>NormativeLibrarianDocument::query()
                ->where('version_status','CANONICAL')
                ->where('audit_status','SAFE')->count(),
            'conflict'=>NormativeLibrarianDocument::query()
                ->where('version_status','CANONICAL')
                ->where('consensus_status','CONFLICT')->count(),
            'insufficient'=>NormativeLibrarianDocument::query()
                ->where('version_status','CANONICAL')
                ->where('consensus_status','INSUFFICIENT')->count(),
            'validated'=>NormativeLibrarianDocument::query()
                ->where('version_status','CANONICAL')
                ->where('classification_status','VALIDATED')->count(),
        ];

        return view(
            'engineering.normative-librarian.canonical-review',
            compact('organization','project','groups','counts','status','q')
        );
    }

    public function validateGroup(
        Request $request,
        Organization $organization,
        Project $project,
        NormativeLibrarianDocument $document
    ): RedirectResponse {
        $this->guardProject($organization,$project);

        abort_unless(
            $document->canonical_group_key && $document->edition_key,
            422
        );

        $validated = $request->validate([
            'discipline'=>['required','string','max:190'],
            'domain'=>['required','string','max:190'],
            'subdomain'=>['nullable','string','max:190'],
        ]);

        DB::transaction(function() use ($document,$validated,$request) {
            $documents = NormativeLibrarianDocument::query()
                ->where('canonical_group_key',$document->canonical_group_key)
                ->where('edition_key',$document->edition_key)
                ->get();

            foreach ($documents as $row) {
                $row->forceFill([
                    'discipline'=>$validated['discipline'],
                    'domain'=>$validated['domain'],
                    'subdomain'=>$validated['subdomain'] ?? null,
                    'classification_status'=>'VALIDATED',
                    'confidence'=>100,
                    'audit_status'=>'SAFE',
                    'audit_risk'=>0,
                    'audit_reason'=>'Validation humaine du groupe canonique.',
                    'consensus_status'=>'VALIDATED_PROPAGATION',
                    'consensus_score'=>100,
                    'consensus_reason'=>'Validation humaine appliquée à la référence/édition canonique.',
                    'consensus_at'=>now(),
                    'reviewed_by_user_id'=>$request->user()?->id,
                    'reviewed_at'=>now(),
                ])->save();
            }
        });

        return back()->with(
            'normative_message',
            'Référence/édition validée pour toutes ses sources.'
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
