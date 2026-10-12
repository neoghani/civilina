<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StructuralLoadCombinationTerm extends Model {
    protected $fillable=['load_combination_id','load_case_id','factor','role'];
    protected function casts():array{return ['factor'=>'decimal:6'];}
    public function combination():BelongsTo{return $this->belongsTo(StructuralLoadCombination::class,'load_combination_id');}
    public function loadCase():BelongsTo{return $this->belongsTo(StructuralLoadCase::class,'load_case_id');}
}
