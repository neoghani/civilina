<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AnalysisModelItem extends Model {
    protected $fillable=['analysis_model_id','technical_object_revision_id','source_snapshot_hash','external_key','metadata'];
    protected function casts():array{return ['metadata'=>'array'];}
    public function analysisModel():BelongsTo{return $this->belongsTo(AnalysisModel::class,'analysis_model_id');}
    public function technicalObjectRevision():BelongsTo{return $this->belongsTo(TechnicalObjectRevision::class,'technical_object_revision_id');}
}
