<?php

namespace App\Services\Engineering\Normative;

use App\Models\EngineeringQaInspection;
use Illuminate\Support\Str;

class NormativeControlCompatibilityService
{
    public function inspectionDomain(EngineeringQaInspection $inspection): ?string
    {
        $text = Str::lower(Str::ascii(implode(' ', [
            (string) ($inspection->work_type ?? ''),
            (string) ($inspection->code ?? ''),
            (string) ($inspection->title ?? ''),
            (string) ($inspection->name ?? ''),
            (string) ($inspection->required_record ?? ''),
        ])));

        if (str_contains($text,'earthwork') || str_contains($text,'road') || str_contains($text,'terrasse') || str_contains($text,'remblai') || str_contains($text,'troxler') || str_contains($text,'plaque') || str_contains($text,'deflex')) return 'earthworks_road';
        if (str_contains($text,'steel') || str_contains($text,'metal') || str_contains($text,'charpente') || str_contains($text,'serrage') || str_contains($text,'boulon')) return 'steel';
        if (str_contains($text,'soud')) return 'welding';
        if (str_contains($text,'concrete') || str_contains($text,'beton') || str_contains($text,'ferraillage') || str_contains($text,'coffrage') || str_contains($text,'eprouvette')) return 'concrete';
        return null;
    }

    public function activityLooksWrong(EngineeringQaInspection $inspection): bool
    {
        $domain = $this->inspectionDomain($inspection);
        $activity = '';

        foreach (['activity_name', 'activity_label'] as $field) {
            if (! empty($inspection->{$field})) $activity .= ' '.$inspection->{$field};
        }

        try {
            if (method_exists($inspection, 'activity') && $inspection->activity) {
                $activity .= ' '.($inspection->activity->name ?? $inspection->activity->title ?? '');
            }
        } catch (\Throwable) {
        }

        $activity = Str::lower(Str::ascii($activity));
        return $domain === 'earthworks_road' && preg_match('/ferraillage|coffrage|beton|poteau/', $activity);
    }
}
