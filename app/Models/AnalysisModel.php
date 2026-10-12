<?php
namespace App\Models;
use App\Enums\AnalysisModelStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class AnalysisModel extends Model {
    protected $fillable=['project_id','design_basis_revision_id','project_standard_profile_id','code','name','solver_key','status','metadata','created_by_user_id'];
    protected function casts():array{return ['status'=>AnalysisModelStatus::class,'metadata'=>'array'];}
    public function project():BelongsTo{return $this->belongsTo(Project::class,'project_id');}
    public function designBasisRevision():BelongsTo{return $this->belongsTo(DesignBasisRevision::class,'design_basis_revision_id');}
    public function projectStandardProfile():BelongsTo{return $this->belongsTo(ProjectStandardProfile::class,'project_standard_profile_id');}
    public function items():HasMany{return $this->hasMany(AnalysisModelItem::class,'analysis_model_id');}
    public function runs():HasMany{return $this->hasMany(AnalysisRun::class,'analysis_model_id');}
}
