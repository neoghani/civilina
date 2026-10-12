<?php
namespace App\Services\Engineering;
use App\Contracts\Engineering\StructuralAnalysisAdapter;
use App\Enums\AnalysisModelStatus;
use App\Enums\AnalysisRunStatus;
use App\Models\AnalysisModel;
use App\Models\AnalysisRun;
use DomainException;
class AnalysisRunService {
    public function prepareRun(AnalysisModel $model,StructuralAnalysisAdapter $adapter):AnalysisRun {
        if($model->status!==AnalysisModelStatus::Approved)throw new DomainException('Only an approved analysis model can produce an analysis run.');
        if($model->solver_key!==$adapter->solverKey())throw new DomainException('Analysis adapter does not match the analysis model solver.');
        $payload=$adapter->buildInputPayload($model);
        $encoded=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
        $next=((int)AnalysisRun::query()->where('analysis_model_id',$model->id)->max('run_no'))+1;
        return AnalysisRun::query()->create(['analysis_model_id'=>$model->id,'run_no'=>$next,'solver_key'=>$adapter->solverKey(),'status'=>AnalysisRunStatus::Prepared,'input_hash'=>hash('sha256',$encoded),'input_payload'=>$payload]);
    }
    public function markStaleIfSourcesChanged(AnalysisRun $run,AnalysisModelService $models):AnalysisRun {
        if($models->hasSupersededSources($run->analysisModel)){$run->status=AnalysisRunStatus::Stale;$run->save();}
        return $run->refresh();
    }
}
