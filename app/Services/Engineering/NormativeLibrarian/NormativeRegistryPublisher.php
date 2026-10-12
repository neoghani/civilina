<?php

namespace App\Services\Engineering\NormativeLibrarian;

use App\Models\NormativeLibrarianDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NormativeRegistryPublisher
{
    public function publishAll(): array
    {
        $stats = ['seen'=>0,'updated'=>0,'not_found'=>0,'skipped'=>0];

        if (! Schema::hasTable('engineering_normative_standards')) {
            throw new \RuntimeException('Table engineering_normative_standards absente.');
        }

        $columns = Schema::getColumnListing('engineering_normative_standards');

        NormativeLibrarianDocument::query()
            ->whereNotNull('reference_code')
            ->whereIn('classification_status',['AUTO_CLASSIFIED','VALIDATED'])
            ->where('document_kind','!=','rejected')
            ->orderBy('id')
            ->chunkById(200, function($documents) use (&$stats,$columns) {
                foreach ($documents as $document) {
                    $stats['seen']++;

                    if (! in_array('canonical_code',$columns,true)) {
                        $stats['skipped']++;
                        continue;
                    }

                    $standard = DB::table('engineering_normative_standards')
                        ->where('canonical_code',$document->reference_code)
                        ->first();

                    if (! $standard) {
                        $document->update(['publish_status'=>'registry_not_found','published_at'=>null]);
                        $stats['not_found']++;
                        continue;
                    }

                    $payload = [];
                    $map = [
                        'title'=>$document->title,
                        'issuing_body'=>$document->issuer,
                        'discipline'=>$document->discipline,
                        'domain'=>$document->domain,
                        'subdomain'=>$document->subdomain,
                        'source_collection'=>$document->source_collection,
                        'classification_status'=>strtolower($document->classification_status),
                    ];

                    foreach ($map as $column=>$value) {
                        if (in_array($column,$columns,true) && $value !== null && $value !== '') {
                            $payload[$column] = $value;
                        }
                    }

                    if (in_array('category',$columns,true) && $document->category_label) $payload['category'] = $document->category_label;
                    if (in_array('subcategory',$columns,true) && $document->subdomain) $payload['subcategory'] = $document->subdomain;
                    if (in_array('updated_at',$columns,true)) $payload['updated_at'] = now();

                    if ($payload) {
                        DB::table('engineering_normative_standards')->where('id',$standard->id)->update($payload);
                    }

                    $document->update([
                        'registry_standard_id'=>$standard->id,
                        'publish_status'=>'published',
                        'published_at'=>now(),
                    ]);

                    $stats['updated']++;
                }
            });

        return $stats;
    }
}
