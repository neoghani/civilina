<?php

namespace App\Http\Controllers;

use App\Models\NormativeLibrarianDocument;
use App\Models\Organization;
use App\Models\Project;
use App\Services\Engineering\NormativeLibrarian\NormativeClassificationRefiner;
use App\Services\Engineering\NormativeLibrarian\NormativeLibrarianScanner;
use App\Services\Engineering\NormativeLibrarian\NormativeManifestExporter;
use App\Services\Engineering\NormativeLibrarian\NormativeRegistryPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NormativeLibrarianController extends Controller
{
    public function index(Request $request, Organization $organization, Project $project): View
    {
        $this->guardProject($organization, $project);

        $q = trim((string) $request->query('q', ''));
        $discipline = trim((string) $request->query('discipline', ''));
        $domain = trim((string) $request->query('domain', ''));
        $subdomain = trim((string) $request->query('subdomain', ''));
        $status = trim((string) $request->query('status', ''));
        $collection = trim((string) $request->query('collection', ''));
        $special = trim((string) $request->query('special', ''));

        $query = NormativeLibrarianDocument::query()
            ->with('duplicateOf')
            ->where('document_kind', '!=', 'rejected')
            ->orderByRaw("case
                when classification_status = 'UNCLASSIFIED' then 3
                when classification_status = 'TO_REVIEW' then 2
                when extraction_status = 'needs_ocr' then 1
                else 0
            end desc")
            ->orderBy('discipline')
            ->orderBy('domain')
            ->orderBy('subdomain')
            ->orderBy('reference_code');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('reference_code', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('subtitle', 'like', "%{$q}%")
                    ->orWhere('classification_index', 'like', "%{$q}%")
                    ->orWhere('filename', 'like', "%{$q}%")
                    ->orWhere('relative_path', 'like', "%{$q}%");
            });
        }

        if ($discipline !== '') $query->where('discipline', $discipline);
        if ($domain !== '') $query->where('domain', $domain);
        if ($subdomain !== '') $query->where('subdomain', $subdomain);
        if ($status !== '') $query->where('classification_status', $status);
        if ($collection !== '') $query->where('source_collection', $collection);

        if ($special === 'ocr') {
            $query->where('extraction_status', 'needs_ocr');
        } elseif ($special === 'duplicates') {
            $query->whereNotNull('duplicate_of_id');
        } elseif ($special === 'published') {
            $query->where('publish_status', 'published');
        }

        $documents = $query->limit(900)->get();

        $disciplines = NormativeLibrarianDocument::query()
            ->whereNotNull('discipline')
            ->where('document_kind', '!=', 'rejected')
            ->selectRaw('discipline, count(*) total')
            ->groupBy('discipline')->orderBy('discipline')->get();

        $domains = NormativeLibrarianDocument::query()
            ->whereNotNull('domain')
            ->where('document_kind', '!=', 'rejected')
            ->selectRaw('domain, count(*) total')
            ->groupBy('domain')->orderBy('domain')->get();

        $subdomains = NormativeLibrarianDocument::query()
            ->whereNotNull('subdomain')
            ->where('document_kind', '!=', 'rejected')
            ->selectRaw('subdomain, count(*) total')
            ->groupBy('subdomain')->orderBy('subdomain')->get();

        $collections = NormativeLibrarianDocument::query()
            ->whereNotNull('source_collection')
            ->selectRaw('source_collection, count(*) total')
            ->groupBy('source_collection')->orderByDesc('total')->get();

        $counts = [
            'documents' => NormativeLibrarianDocument::query()->where('document_kind', '!=', 'rejected')->count(),
            'classified' => NormativeLibrarianDocument::query()->where('classification_status', 'AUTO_CLASSIFIED')->count(),
            'review' => NormativeLibrarianDocument::query()->where('classification_status', 'TO_REVIEW')->count(),
            'unclassified' => NormativeLibrarianDocument::query()->where('classification_status', 'UNCLASSIFIED')->count(),
            'validated' => NormativeLibrarianDocument::query()->where('classification_status', 'VALIDATED')->count(),
            'ocr' => NormativeLibrarianDocument::query()->where('extraction_status', 'needs_ocr')->count(),
            'duplicates' => NormativeLibrarianDocument::query()->whereNotNull('duplicate_of_id')->count(),
            'published' => NormativeLibrarianDocument::query()->where('publish_status', 'published')->count(),
        ];

        return view('engineering.normative-librarian.index', compact(
            'organization', 'project', 'documents', 'disciplines', 'domains',
            'subdomains', 'collections', 'counts', 'q', 'discipline', 'domain',
            'subdomain', 'status', 'collection', 'special'
        ));
    }

    public function show(
        Organization $organization,
        Project $project,
        NormativeLibrarianDocument $document
    ): View {
        $this->guardProject($organization, $project);

        return view('engineering.normative-librarian.show', compact(
            'organization', 'project', 'document'
        ));
    }

    public function inline(
        Organization $organization,
        Project $project,
        NormativeLibrarianDocument $document
    ): BinaryFileResponse {
        $this->guardProject($organization, $project);

        $path = $document->source_path;

        abort_unless(is_string($path) && File::exists($path), 404);

        $extension = strtolower((string) $document->extension);

        abort_unless(in_array($extension, ['pdf', 'txt', 'md'], true), 415);

        $mime = $extension === 'pdf'
            ? 'application/pdf'
            : 'text/plain; charset=UTF-8';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update(
        Request $request,
        Organization $organization,
        Project $project,
        NormativeLibrarianDocument $document
    ): RedirectResponse {
        $this->guardProject($organization, $project);

        $validated = $request->validate([
            'reference_code' => ['nullable', 'string', 'max:190'],
            'issuer' => ['nullable', 'string', 'max:120'],
            'category_label' => ['nullable', 'string', 'max:190'],
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'publication_month' => ['nullable', 'string', 'max:40'],
            'publication_year' => ['nullable', 'integer', 'min:1800', 'max:2200'],
            'classification_index' => ['nullable', 'string', 'max:190'],
            'ics_text' => ['nullable', 'string', 'max:500'],
            'discipline' => ['nullable', 'string', 'max:190'],
            'domain' => ['nullable', 'string', 'max:190'],
            'subdomain' => ['nullable', 'string', 'max:190'],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $ics = preg_split(
            '/[;,\s]+/',
            (string) ($validated['ics_text'] ?? ''),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        unset($validated['ics_text']);

        $document->forceFill(array_merge($validated, [
            'ics_codes' => array_values(array_unique($ics ?: [])),
            'reference_normalized' => ! empty($validated['reference_code'])
                ? preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($validated['reference_code']))
                : $document->reference_normalized,
            'classification_status' => 'VALIDATED',
            'confidence' => 100,
        ]))->save();

        return back()->with(
            'normative_message',
            'Fiche normative validée par l’ingénieur.'
        );
    }

    public function scan(
        Request $request,
        Organization $organization,
        Project $project,
        NormativeLibrarianScanner $scanner
    ): RedirectResponse {
        $this->guardProject($organization, $project);

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'force' => ['nullable', 'boolean'],
        ]);

        $stats = $scanner->scan(
            null,
            (int) ($validated['limit'] ?? 100),
            (bool) ($validated['force'] ?? false)
        );

        return back()->with(
            'normative_message',
            'Scan local terminé : '.json_encode($stats, JSON_UNESCAPED_UNICODE)
        );
    }

    public function refine(
        Request $request,
        Organization $organization,
        Project $project,
        NormativeClassificationRefiner $refiner
    ): RedirectResponse {
        $this->guardProject($organization, $project);

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'status' => ['nullable', 'in:UNCLASSIFIED,TO_REVIEW'],
        ]);

        $stats = $refiner->run(
            (int) ($validated['limit'] ?? 500),
            (string) ($validated['status'] ?? 'UNCLASSIFIED')
        );

        return back()->with(
            'normative_message',
            'Deuxième passe : '.json_encode($stats, JSON_UNESCAPED_UNICODE)
        );
    }

    public function publish(
        Organization $organization,
        Project $project,
        NormativeRegistryPublisher $publisher
    ): RedirectResponse {
        $this->guardProject($organization, $project);

        $stats = $publisher->publishAll();

        return back()->with(
            'normative_message',
            'Publication contrôlée : '.json_encode($stats, JSON_UNESCAPED_UNICODE)
        );
    }

    public function export(
        Organization $organization,
        Project $project,
        NormativeManifestExporter $exporter
    ): RedirectResponse {
        $this->guardProject($organization, $project);

        $path = $exporter->export();

        return back()->with(
            'normative_message',
            'Manifeste JSON créé : '.$path
        );
    }

    private function guardProject(Organization $organization, Project $project): void
    {
        if (isset($project->organization_id)) {
            abort_unless(
                (int) $project->organization_id === (int) $organization->id,
                404
            );
        }
    }
}
