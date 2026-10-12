<?php

namespace App\Services\Engineering\NormativeLibrarian;

use App\Models\NormativeLibrarianDocument;
use Illuminate\Support\Facades\File;

class NormativeManifestExporter
{
    public function export(?string $output = null): string
    {
        $output ??= storage_path('app/private/engineering/normative_manifest_'.now()->format('Ymd_His').'.json');
        File::ensureDirectoryExists(dirname($output));

        $documents = NormativeLibrarianDocument::query()
            ->orderBy('discipline')->orderBy('domain')->orderBy('subdomain')->orderBy('reference_code')
            ->get()
            ->map(fn($d)=>[
                'reference'=>$d->reference_code,
                'issuer'=>$d->issuer,
                'category'=>$d->category_label,
                'title'=>$d->title,
                'subtitle'=>$d->subtitle,
                'month'=>$d->publication_month,
                'year'=>$d->publication_year,
                'classification_index'=>$d->classification_index,
                'ics'=>$d->ics_codes,
                'discipline'=>$d->discipline,
                'domain'=>$d->domain,
                'subdomain'=>$d->subdomain,
                'source_collection'=>$d->source_collection,
                'source_path'=>$d->source_path,
                'sha256'=>$d->sha256,
                'classification_status'=>$d->classification_status,
                'confidence'=>(float)$d->confidence,
                'extraction_status'=>$d->extraction_status,
            ])->values()->all();

        File::put($output, json_encode([
            'schema'=>'civilina.normative-librarian.manifest.v1',
            'generated_at'=>now()->toIso8601String(),
            'count'=>count($documents),
            'documents'=>$documents,
        ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

        return $output;
    }
}
