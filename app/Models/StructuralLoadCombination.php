<?php
namespace App\Models;
use App\Enums\StructuralLimitState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class StructuralLoadCombination extends Model {
    protected $fillable=['project_id','code','name','limit_state','combination_type','metadata'];
    protected function casts():array{return ['limit_state'=>StructuralLimitState::class,'metadata'=>'array'];}
    public function project():BelongsTo{return $this->belongsTo(Project::class,'project_id');}
    public function terms():HasMany{return $this->hasMany(StructuralLoadCombinationTerm::class,'load_combination_id');}
}
