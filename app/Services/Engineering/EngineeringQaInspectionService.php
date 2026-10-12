<?php

namespace App\Services\Engineering;

use App\Models\EngineeringQaHandover;
use App\Models\EngineeringQaInspection;
use App\Models\EngineeringItp;
use Illuminate\Support\Facades\DB;

class EngineeringQaInspectionService
{
    public function __construct(private readonly EngineeringQaWorkTypeCatalog $catalog)
    {
    }

    public function generate(
        string $organizationId,
        string $projectId,
        string $workType,
        string $scopeType,
        ?string $scopeRef,
        string $scopeLabel,
        ?EngineeringItp $itp,
        ?string $createdBy
    ): array {
        $templates = $this->catalog->templates($workType);

        if ($templates === []) {
            throw new \InvalidArgumentException('Type de travaux QA/QC inconnu.');
        }

        return DB::transaction(function () use (
            $organizationId, $projectId, $workType, $scopeType, $scopeRef, $scopeLabel, $itp, $createdBy, $templates
        ): array {
            $created = [];

            foreach ($templates as $index => $template) {
                $inspectionCode = $this->uniqueInspectionCode(
                    $organizationId,
                    $projectId,
                    strtoupper(substr(str_replace('_', '-', $workType), 0, 12)).'-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)
                );

                $created[] = EngineeringQaInspection::create([
                    'organization_id' => $organizationId,
                    'project_id' => $projectId,
                    'engineering_itp_id' => $itp?->getKey(),
                    'template_key' => $template['key'],
                    'work_type' => $workType,
                    'inspection_code' => $inspectionCode,
                    'title' => $template['title'],
                    'record_type' => $template['record_type'],
                    'register_type' => $template['register_type'],
                    'checkpoint_type' => $template['checkpoint_type'],
                    'scope_type' => $scopeType,
                    'scope_ref' => $scopeRef,
                    'scope_label' => $scopeLabel,
                    'authorization_status' => $template['requires_client_authorization']
                        ? EngineeringQaInspection::AUTH_REQUESTED
                        : EngineeringQaInspection::AUTH_NONE,
                    'acceptance_criteria' => null,
                    'requirement_source' => null,
                    'created_by' => $createdBy,
                ]);
            }

            $handovers = [];
            foreach ($this->catalog->handoverTemplates($workType) as $handoverTemplate) {
                $code = $this->uniqueHandoverCode($organizationId, $projectId, $handoverTemplate['code_suffix']);
                $handovers[] = EngineeringQaHandover::create([
                    'organization_id' => $organizationId,
                    'project_id' => $projectId,
                    'code' => $code,
                    'from_work_type' => $handoverTemplate['from_work_type'],
                    'to_work_type' => $handoverTemplate['to_work_type'],
                    'scope_type' => $scopeType,
                    'scope_ref' => $scopeRef,
                    'scope_label' => $scopeLabel,
                    'criteria' => $handoverTemplate['criteria'],
                    'created_by' => $createdBy,
                ]);
            }

            return ['inspections' => $created, 'handovers' => $handovers];
        });
    }

    private function uniqueInspectionCode(string $organizationId, string $projectId, string $base): string
    {
        $candidate = $base;
        $i = 1;
        while (EngineeringQaInspection::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('inspection_code', $candidate)
            ->exists()) {
            $i++;
            $candidate = $base.'-'.$i;
        }
        return $candidate;
    }

    private function uniqueHandoverCode(string $organizationId, string $projectId, string $base): string
    {
        $candidate = $base;
        $i = 1;
        while (EngineeringQaHandover::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('code', $candidate)
            ->exists()) {
            $i++;
            $candidate = $base.'-'.$i;
        }
        return $candidate;
    }
}
