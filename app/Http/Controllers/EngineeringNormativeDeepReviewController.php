<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Services\Engineering\EngineeringTenantAccessService;
use App\Services\Engineering\NormativeLibrarian\NormativeAtomicPublicationGateService;
use App\Services\Engineering\NormativeLibrarian\NormativeAtomicStatementReviewService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EngineeringNormativeDeepReviewController extends Controller
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
        NormativeAtomicPublicationGateService $gate,
        EngineeringTenantAccessService $access
    ) {
        $this->guard($request, $organization, $project, $access);
        $status = mb_strtoupper((string) $request->query('status', 'CANDIDATE'));
        $type = mb_strtoupper((string) $request->query('type', ''));
        $q = trim((string) $request->query('q', ''));

        $query = $gate->query()
            ->orderByDesc('a.segmentation_confidence')
            ->orderBy('a.id');

        if ($status !== 'ALL') {
            $query->where('a.review_status', $status);
        }
        if ($type !== '') {
            $query->where('a.statement_type', $type);
        }
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('a.statement_text', 'like', "%{$q}%")
                    ->orWhere('a.reference_code', 'like', "%{$q}%")
                    ->orWhere('s.heading', 'like', "%{$q}%");
            });
        }

        $items = $query->paginate(40)->withQueryString();
        $metrics = [
            'candidate' => DB::table('civilina_normative_atomic_statements')->where('review_status', 'CANDIDATE')->count(),
            'validated' => DB::table('civilina_normative_atomic_statements')->where('review_status', 'VALIDATED')->count(),
            'rejected' => DB::table('civilina_normative_atomic_statements')->where('review_status', 'REJECTED')->count(),
        ];

        return view('engineering.normative-deep-review.index', compact(
            'organization',
            'project',
            'items',
            'metrics',
            'status',
            'type',
            'q'
        ));
    }

    public function show(
        Request $request,
        Organization $organization,
        Project $project,
        int $candidate,
        NormativeAtomicPublicationGateService $gate,
        EngineeringTenantAccessService $access
    ) {
        $this->guard($request, $organization, $project, $access);
        $item = $gate->query()->where('a.id', $candidate)->first();
        abort_unless($item, 404);

        $item->section_text = DB::table('civilina_normative_deep_sections')
            ->where('id', $item->section_id)
            ->value('section_text');
        $item->gate = $gate->evaluate($item);
        $history = DB::table('civilina_normative_atomic_review_events')
            ->where('atomic_statement_id', $item->id)
            ->orderByDesc('id')
            ->get();

        return view('engineering.normative-deep-review.show', compact(
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
        int $candidate,
        NormativeAtomicStatementReviewService $service,
        EngineeringTenantAccessService $access
    ) {
        $this->guard($request, $organization, $project, $access, true);
        $decision = mb_strtoupper((string) $request->input('decision'));
        abort_unless(in_array($decision, NormativeAtomicStatementReviewService::DECISIONS, true), 422);

        $rules = ['note' => 'nullable|string|max:4000'];
        if ($decision === 'REJECTED') {
            $rules['note'] = 'required|string|max:4000';
        }
        $data = $request->validate($rules);

        try {
            $service->decide(
                $candidate,
                $decision,
                $data['note'] ?? null,
                $request->user()?->id,
                $request->user()?->name
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['decision' => $exception->getMessage()]);
        }

        return back()->with('success', "Statut atomique mis a jour : {$decision}");
    }
}
