<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StructuralBoundaryCondition extends Model {
    protected $fillable=['project_id','technical_object_revision_id','condition_type','degrees_of_freedom','stiffness','source_type','source_reference','metadata'];
    protected function casts():array{return ['degrees_of_freedom'=>'array','stiffness'=>'array','metadata'=>'array'];}
    public function project():BelongsTo{return $this->belongsTo(Project::class,'project_id');}
    public function technicalObjectRevision():BelongsTo{return $this->belongsTo(TechnicalObjectRevision::class,'technical_object_revision_id');}
}
