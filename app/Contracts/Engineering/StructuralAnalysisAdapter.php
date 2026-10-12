<?php
namespace App\Contracts\Engineering;
use App\Models\AnalysisModel;
interface StructuralAnalysisAdapter {
    public function solverKey(): string;
    public function buildInputPayload(AnalysisModel $analysisModel): array;
}
