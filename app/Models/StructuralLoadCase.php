<?php
namespace App\Models;
use App\Enums\StructuralActionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class StructuralLoadCase extends Model {
    protected $fillable=['project_id','code','name','action_type','self_weight_multiplier','metadata'];
    protected function casts():array{return ['action_type'=>StructuralActionType::class,'self_weight_multiplier'=>'decimal:4','metadata'=>'array'];}
    public function project():BelongsTo{return $this->belongsTo(Project::class,'project_id');}
    public function loads():HasMany{return $this->hasMany(StructuralLoad::class,'load_case_id');}
}
