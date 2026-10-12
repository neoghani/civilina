<?php
namespace App\Models;
use App\Enums\AnalysisRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AnalysisRun extends Model {
    protected $fillable=['analysis_model_id','run_no','solver_key','status','input_hash','input_payload','external_reference','result_summary','started_at','finished_at','failure_message'];
    protected function casts():array{return ['run_no'=>'integer','status'=>AnalysisRunStatus::class,'input_payload'=>'array','result_summary'=>'array','started_at'=>'datetime','finished_at'=>'datetime'];}
    public function analysisModel():BelongsTo{return $this->belongsTo(AnalysisModel::class,'analysis_model_id');}
}
