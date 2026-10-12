<?php

namespace App\Services\Engineering\Normative;

use App\Models\EngineeringNormativeSource;
use App\Models\EngineeringNormativeStandard;

class NormativeTaxonomyIndexer
{
    public function __construct(private NormativeTaxonomyService $taxonomy)
    {
    }

    public function refresh(): array
    {
        $sourceCount = 0;
        $standardCount = 0;

        EngineeringNormativeSource::query()->with('standard')->orderBy('id')->chunkById(200, function ($sources) use (&$sourceCount, &$standardCount) {
            foreach ($sources as $source) {
                $classification = $this->taxonomy->classify(
                    (string) $source->relative_path,
                    (string) ($source->detected_title ?? ''),
                    (string) ($source->domain ?? $source->standard?->domain ?? '')
                );

                $source->forceFill([
                    'category' => $classification['category'],
                    'subcategory' => $classification['subcategory'],
                    'domain' => $classification['domain'],
                ])->save();

                $sourceCount++;

                if ($source->standard) {
                    $standard = $source->standard;
                    $updates = [];
                    if (! $standard->category) $updates['category'] = $classification['category'];
                    if (! $standard->subcategory && $classification['subcategory']) $updates['subcategory'] = $classification['subcategory'];
                    if (! $standard->domain && $classification['domain']) $updates['domain'] = $classification['domain'];
                    if ($updates) {
                        $standard->forceFill($updates)->save();
                        $standardCount++;
                    }
                }
            }
        });

        EngineeringNormativeStandard::query()->with('sources:id,standard_id,category,subcategory,domain')->chunkById(150, function ($standards) use (&$standardCount) {
            foreach ($standards as $standard) {
                $category = $standard->sources->pluck('category')->filter()->countBy()->sortDesc()->keys()->first();
                $domain = $standard->sources->pluck('domain')->filter()->countBy()->sortDesc()->keys()->first();
                $updates = [];
                if ($category && $standard->category !== $category) $updates['category'] = $category;
                if ($domain && $standard->domain !== $domain) $updates['domain'] = $domain;
                if ($updates) {
                    $standard->forceFill($updates)->save();
                    $standardCount++;
                }
            }
        });

        return ['sources' => $sourceCount, 'standards' => $standardCount];
    }
}
