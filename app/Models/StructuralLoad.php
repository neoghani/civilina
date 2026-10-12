<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StructuralLoad extends Model {
    protected $fillable=['project_id','load_case_id','technical_object_revision_id','load_type','coordinate_system','values','source_type','source_reference','metadata'];
    protected function casts():array{return ['values'=>'array','metadata'=>'array'];}
    public function project():BelongsTo{return $this->belongsTo(Project::class,'project_id');}
    public function loadCase():BelongsTo{return $this->belongsTo(StructuralLoadCase::class,'load_case_id');}
    public function technicalObjectRevision():BelongsTo{return $this->belongsTo(TechnicalObjectRevision::class,'technical_object_revision_id');}
}
