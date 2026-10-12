<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Services\Engineering\EngineeringTenantAccessService;
use App\Services\Engineering\NormativeLibrarian\NormativeSourceReviewService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EngineeringNormativeSourceReviewController extends Controller
{
    private function guard(
        Request $request,
        Organization $organization,
        Project $project,
        EngineeringTenantAccessService $access,
        bool $approve = false
    ): void
    {
        $approve
            ? $access->authorizeApprove($request->user(), $organization)
            : $access->authorizeView($request->user(), $organization);
        if (isset($project->organization_id)) {
            abort_unless((int) $project->organization_id === (int) $organization->id, 404);
        }
    }

    public function index(
        Request $request,
        Organization $organization,
        Project $project,
        EngineeringTenantAccessService $access
    )
    {
        $this->guard($request, $organization, $project, $access);
        $authority = mb_strtoupper((string) $request->query('authority', 'ALL'));
        $decision = mb_strtoupper((string) $request->query('decision', 'ALL'));

        $query = $this->sourceQuery()
            ->selectSub(function ($sub) {
                $sub->from('civilina_normative_atomic_statements as a')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('a.deep_document_id', 'sr.deep_document_id');
            }, 'statement_count')
            ->orderBy('sr.detected_authority')
            ->orderBy('sr.reference_code');

        if ($authority !== 'ALL') {
            $query->where('sr.detected_authority', $authority);
        }
        if ($decision !== 'ALL') {
            $query->where('sr.review_status', $decision);
        }

        $items = $query->paginate(25)->withQueryString();
        $metrics = [];
        foreach (['PRIMARY_CANDIDATE', 'BLOCKED_EDITION', 'SECONDARY_DETECTED', 'UNVERIFIED'] as $value) {
            $metrics[$value] = DB::table('civilina_normative_atomic_source_reviews')
                ->where('detected_authority', $value)
                ->count();
        }

        return view('engineering.normative-source-review.index', compact(
            'organization',
            'project',
            'items',
            'metrics',
            'authority',
            'decision'
        ));
    }

    public function show(
        Request $request,
        Organization $organization,
        Project $project,
        int $document,
        EngineeringTenantAccessService $access
    )
    {
        $this->guard($request, $organization, $project, $access);
        $item = $this->sourceQuery()
            ->selectSub(function ($sub) {
                $sub->from('civilina_normative_atomic_statements as a')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('a.deep_document_id', 'sr.deep_document_id');
            }, 'statement_count')
            ->where('sr.deep_document_id', $document)
            ->first();
        abort_unless($item, 404);

        $history = DB::table('civilina_normative_source_review_events')
            ->where('deep_document_id', $document)
            ->orderByDesc('id')
            ->get();

        return view('engineering.normative-source-review.show', compact(
            'organization',
            'project',
            'item',
            'history'
        ));
    }

    public function decide(
        Request $request,
        Organization $organization,
        Project $project,
        int $document,
        NormativeSourceReviewService $service,
        EngineeringTenantAccessService $access
    ) {
        $this->guard($request, $organization, $project, $access, true);
        $data = $request->validate([
            'decision' => 'required|string|in:PRIMARY_VALIDATED,SECONDARY_REJECTED,NEEDS_REVIEW',
            'note' => 'required|string|max:4000',
        ]);

        try {
            $service->decide(
                $document,
                $data['decision'],
                $data['note'],
                $request->user()?->id,
                $request->user()?->name
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['decision' => $exception->getMessage()]);
        }

        return back()->with('success', 'Decision de source enregistree et historisee.');
    }

    private function sourceQuery()
    {
        return DB::table('civilina_normative_atomic_source_reviews as sr')
            ->join('civilina_normative_deep_documents as d', 'd.id', '=', 'sr.deep_document_id')
            ->leftJoin('civilina_normative_librarian_documents as l', 'l.id', '=', 'sr.document_id')
            ->select([
                'sr.*',
                'd.source_kind',
                'd.raw_text',
                'l.title',
                'l.source_path',
                'l.relative_path',
                'l.cover_text',
                'l.ics_codes',
                'l.domain',
                'l.discipline',
                'l.metadata',
            ]);
    }
}
